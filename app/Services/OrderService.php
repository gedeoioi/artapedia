<?php

namespace App\Services;

use App\Jobs\DispatchOrderToSupplier;
use App\Models\BalanceMutation;
use App\Models\Invoice;
use App\Models\PaymentGatewayConfig;
use App\Models\Product;
use App\Models\SupplierConfig;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class OrderService
{
    public function __construct(
        protected BalanceService $balances,
        protected PaymentService $payments,
    ) {}

    public function checkout(array $data, ?User $user = null): Transaction
    {
        return DB::transaction(function () use ($data, $user) {
            /** @var Product $product */
            $product = Product::whereKey($data['product_id'])
                ->where('is_active', true)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $product->in_stock) {
                throw new \RuntimeException('Produk sedang kosong.');
            }

            $level = $user?->level ?? 'guest';
            $quote = $this->payments->quote($product, $user, $data['gateway_code'] ?? 'balance');
            $method = $data['gateway_code'] ?? 'balance';

            if ($user && $user->status === 'suspended') {
                throw new \RuntimeException('Akun disuspend.');
            }

            $supplier = SupplierConfig::whereKey($product->supplier_config_id)->first();

            $trx = Transaction::create([
                'invoice_code' => $this->payments->invoiceCode(),
                'user_id' => $user?->id,
                'product_id' => $product->id,
                'supplier_config_id' => $product->supplier_config_id,
                'payment_gateway_code' => $method,
                'target_user_id' => $data['target_user_id'],
                'target_zone' => $data['target_zone'] ?? null,
                'nickname' => $data['nickname'] ?? null,
                'quantity' => $data['quantity'] ?? 1,
                'cost_price' => $product->costForLevel($level) * ($data['quantity'] ?? 1),
                'sell_price' => $quote['sell_price'] * ($data['quantity'] ?? 1),
                'admin_fee' => $quote['admin_fee'],
                'gateway_fee' => $quote['gateway_fee'],
                'total_amount' => $quote['total'] * ($data['quantity'] ?? 1),
                'payment_method' => $method,
                'status' => Transaction::STATUS_PENDING,
                'buyer_phone' => $data['buyer_phone'] ?? $user?->phone,
                'buyer_email' => $data['buyer_email'] ?? $user?->email,
            ]);
            $trx->recalculateProfit();
            $trx->save();

            if ($method === 'balance') {
                if (! $user) {
                    throw new \RuntimeException('Checkout saldo wajib login.');
                }
                $this->balances->debit($user, $trx->total_amount, 'Order '.$trx->invoice_code.' '.$product->name, $trx->id);
                $trx->status = Transaction::STATUS_PAID;
                $trx->paid_at = now();
                $trx->save();

                DispatchOrderToSupplier::dispatch($trx->id);

                return $trx->fresh();
            }

            $gw = PaymentGatewayConfig::where('code', $method)->where('is_active', true)->firstOrFail();
            $gateway = ProviderFactory::gatewayFor($gw);
            $reference = $this->payments->referenceId('PAY');
            $result = $gateway->createPayment([
                'reference_id' => $reference,
                'amount' => $trx->total_amount,
                'description' => $product->name.' / '.$trx->invoice_code,
                'customer_email' => $trx->buyer_email,
                'customer_phone' => $trx->buyer_phone,
                'customer_name' => $user?->name ?? 'Guest',
            ]);

            $trx->payment_reference = $reference;
            $trx->payment_payload = $result['raw'] ?? null;
            $trx->save();

            Invoice::create([
                'transaction_id' => $trx->id,
                'invoice_code' => $trx->invoice_code,
                'gateway_code' => $gw->code,
                'reference_id' => $reference,
                'amount' => $trx->total_amount,
                'status' => 'pending',
                'expired_at' => now()->addHour(),
                'payload' => $result['raw'] ?? null,
            ]);

            return $trx->fresh();
        });
    }

    public function dispatchToSupplier(int $transactionId, ?int $preferredSupplierId = null, bool $manual = false): Transaction
    {
        $trx = Transaction::with(['product', 'supplier'])->findOrFail($transactionId);

        if (in_array($trx->status, [Transaction::STATUS_SUCCESS, Transaction::STATUS_FAILED, Transaction::STATUS_EXPIRED], true)) {
            return $trx;
        }

        // Idempotency: kalau supplier_trx_id sudah ada, JANGAN order ulang.
        // Cukup kembalikan — status final ditentukan oleh pollStatus().
        if ($trx->supplier_trx_id) {
            return $trx;
        }

        // Atomic claim: hanya 1 worker yang boleh meneruskan ke order HTTP.
        // Mencegah double-order ke supplier saat 2 job/cron berjalan bersamaan.
        $claimed = Transaction::whereKey($trx->id)
            ->whereNull('supplier_trx_id')
            ->whereIn('status', [Transaction::STATUS_PENDING, Transaction::STATUS_PAID, Transaction::STATUS_PROCESSING])
            ->update(['status' => Transaction::STATUS_PROCESSING, 'supplier_status' => 'claiming_order']);

        if (! $claimed) {
            return $trx->fresh();
        }

        $trx = Transaction::with(['product', 'supplier'])->findOrFail($transactionId);

        $candidates = SupplierConfig::activeOrdered();
        if ($preferredSupplierId) {
            $candidates = $candidates->sortBy(fn ($s) => $s->id === $preferredSupplierId ? 0 : 1)->values();
        }

        foreach ($candidates as $supplier) {
            try {
                $provider = ProviderFactory::supplierFor($supplier);
                $target = $trx->target_zone
                    ? $trx->target_user_id.'|'.$trx->target_zone
                    : $trx->target_user_id;

                $orderOptions = ['trx_id' => $trx->id];

                if ($provider instanceof \App\Suppliers\DigiflazzProvider) {
                    // Digiflazz: ref_id HARUS unik & stabil per transaksi.
                    // Retry / cek status memakai ref_id yang sama -> tidak double charge.
                    // Format: AP-{trx_id} (huruf/angka, aman untuk Digiflazz).
                    $orderOptions['ref_id'] = 'AP-'.$trx->id;
                }

                $res = $provider->order($trx->product->supplier_code, $target, $orderOptions);

                if (! ($res['result'] ?? false)) {
                    continue;
                }

                $trx->supplier_config_id = $supplier->id;
                $trx->supplier_trx_id = $res['data']['trxid'] ?? $res['trxid'] ?? (string) $trx->id;
                $trx->supplier_status = $res['data']['status'] ?? 'processing';
                $trx->processed_at = now();
                $trx->status = Transaction::STATUS_PROCESSING;
                if (! empty($trx->notes) || $manual) {
                    $trx->notes = trim(($trx->notes ?? '').($manual ? ' [manual by admin]' : ''));
                }
                $trx->save();

                return $trx->fresh();
            } catch (\Throwable $e) {
                report($e);
                continue;
            }
        }

        $trx->supplier_status = 'all_suppliers_failed';
        $trx->save();

        return $trx->fresh();
    }

    public function pollStatus(int $transactionId): Transaction
    {
        $trx = Transaction::with('supplier')->findOrFail($transactionId);

        if (! $trx->supplier_trx_id || ! $trx->supplier) {
            return $trx;
        }

        $provider = ProviderFactory::supplierFor($trx->supplier);

        // Digiflazz: cek status prepaid = topup ulang dengan ref_id yang sama,
        // butuh buyer_sku_code + customer_no asli.
        $statusOptions = [];
        if ($provider instanceof \App\Suppliers\DigiflazzProvider) {
            $statusOptions = [
                'buyer_sku_code' => $trx->product->supplier_code,
                'customer_no' => $trx->target_zone
                    ? $trx->target_user_id.'|'.$trx->target_zone
                    : $trx->target_user_id,
            ];
        }

        $res = $provider->checkStatus($trx->supplier_trx_id, $statusOptions);
        $status = strtolower($res['data']['status'] ?? $res['status'] ?? '');

        return DB::transaction(function () use ($trx, $status, $res) {
            $locked = Transaction::whereKey($trx->id)->lockForUpdate()->firstOrFail();

            if ($locked->isFinal()) {
                return $locked;
            }

            $locked->supplier_status = $status;
            $locked->payment_payload = array_merge($locked->payment_payload ?? [], ['poll' => $res]);

            if (in_array($status, ['success', 'sukses'], true)) {
                $locked->status = Transaction::STATUS_SUCCESS;
            } elseif (in_array($status, ['failed', 'gagal', 'batal'], true)) {
                $locked->status = Transaction::STATUS_FAILED;
                if ($locked->payment_method === 'balance' && $locked->user_id) {
                    $this->balances->credit(
                        $locked->user,
                        $locked->total_amount,
                        BalanceMutation::TYPE_REFUND,
                        'Refund '.$locked->invoice_code.' supplier gagal',
                        $locked->id
                    );
                }
            }
            $locked->save();

            return $locked->fresh();
        });
    }

    public function manualRefund(int $transactionId): Transaction
    {
        return DB::transaction(function () use ($transactionId) {
            $trx = Transaction::whereKey($transactionId)->lockForUpdate()->firstOrFail();

            if ($trx->status === Transaction::STATUS_FAILED) {
                return $trx;
            }

            $trx->status = Transaction::STATUS_FAILED;
            $trx->save();

            if ($trx->payment_method === 'balance' && $trx->user_id && $trx->paid_at) {
                $this->balances->credit(
                    $trx->user,
                    $trx->total_amount,
                    BalanceMutation::TYPE_REFUND,
                    'Refund manual '.$trx->invoice_code,
                    $trx->id
                );
            }

            return $trx->fresh();
        });
    }
}
