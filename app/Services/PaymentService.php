<?php

namespace App\Services;

use App\Jobs\DispatchOrderToSupplier;
use App\Models\BalanceMutation;
use App\Models\Invoice;
use App\Models\PaymentGatewayConfig;
use App\Models\Product;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PaymentService
{
    public function __construct(protected BalanceService $balances) {}

    public function quote(Product $product, ?User $user, ?string $gatewayCode, int $quantity = 1): array
    {
        $quantity = max(1, $quantity);
        $sell = $user ? $user->priceFor($product) : (int) $product->price_guest;
        $subtotal = $sell * $quantity;
        $gateway = $gatewayCode && $gatewayCode !== 'balance'
            ? PaymentGatewayConfig::where('code', $gatewayCode)->where('is_active', true)->first()
            : null;
        $gatewayFee = $gateway?->feeFor($subtotal) ?? 0;

        $adminFee = 0;

        return [
            'sell_price' => $sell,
            'subtotal' => $subtotal,
            'admin_fee' => $adminFee,
            'gateway_fee' => $gatewayFee,
            'total' => $subtotal + $adminFee + $gatewayFee,
        ];
    }

    public function invoiceCode(): string
    {
        return 'INV-'.now()->format('Ymd').'-'.Str::upper(Str::random(8));
    }

    public function referenceId(string $prefix = 'AP'): string
    {
        return $prefix.'-'.now()->format('YmdHis').'-'.Str::upper(Str::random(6));
    }

    public function topupBalance(User $user, int $amount, string $gatewayCode, ?string $customerPhone = null): Transaction
    {
        return DB::transaction(function () use ($user, $amount, $gatewayCode, $customerPhone) {
            $locked = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $gw = PaymentGatewayConfig::where('code', $gatewayCode)->where('is_active', true)->firstOrFail();
            $fee = $gw->feeFor($amount);
            $phone = trim((string) ($customerPhone ?: $locked->phone));

            if (! preg_match('/^(?:\+?62|0)8[0-9]{8,12}$/', $phone)) {
                throw new \RuntimeException('Nomor HP akun belum diisi atau tidak valid. Silakan perbarui profil terlebih dahulu.');
            }

            $trx = Transaction::create([
                'invoice_code' => $this->invoiceCode(),
                'user_id' => $locked->id,
                'product_id' => null,
                'payment_gateway_code' => $gw->code,
                'target_user_id' => 'TOPUP',
                'quantity' => 1,
                'cost_price' => 0,
                'sell_price' => $amount,
                'admin_fee' => 0,
                'gateway_fee' => $fee,
                'total_amount' => $amount + $fee,
                'profit' => -$fee,
                'payment_method' => $gw->code,
                'status' => Transaction::STATUS_PENDING,
                'buyer_phone' => $phone,
                'buyer_email' => $locked->email,
                'meta' => ['kind' => 'topup'],
            ]);
            $trx->profit = -$fee;
            $trx->save();

            $gateway = ProviderFactory::gatewayFor($gw);
            $result = $gateway->createPayment([
                'reference_id' => $this->referenceId('TOPUP'),
                'amount' => $trx->total_amount,
                'description' => 'Topup saldo '.$amount,
                'customer_email' => $locked->email,
                'customer_phone' => $phone,
                'customer_name' => $locked->name,
                'success_url' => route('payment.show', $trx->invoice_code),
                'failure_url' => route('topup.create'),
            ]);

            if (! ($result['ok'] ?? false) || empty($result['reference_id'])) {
                $message = trim((string) ($result['message'] ?? ''));
                throw new \RuntimeException($message !== '' ? $gw->name.': '.$message : 'Gateway gagal membuat pembayaran. Silakan coba metode lain.');
            }

            $payload = array_merge($result['raw'] ?? [], [
                '_checkout_url' => $result['pay_url'] ?? null,
                '_gateway_reference' => $result['gateway_ref'] ?? null,
            ]);
            $trx->payment_reference = $result['reference_id'];
            $trx->payment_payload = $payload;
            $trx->save();

            Invoice::create([
                'transaction_id' => $trx->id,
                'invoice_code' => $trx->invoice_code,
                'gateway_code' => $gw->code,
                'reference_id' => $result['reference_id'],
                'amount' => $trx->total_amount,
                'status' => 'pending',
                'expired_at' => now()->addHour(),
                'payload' => $payload,
            ]);

            return $trx;
        });
    }

    public function markPaid(string $referenceId, string $gatewayCode, array $raw = [], ?int $paidAmount = null): ?Transaction
    {
        return DB::transaction(function () use ($referenceId, $gatewayCode, $raw, $paidAmount) {
            $trx = Transaction::where('payment_reference', $referenceId)->lockForUpdate()->first();
            if (! $trx) {
                return null;
            }

            if (! hash_equals((string) $trx->payment_gateway_code, $gatewayCode)) {
                throw new \UnexpectedValueException('Gateway callback tidak sesuai dengan transaksi.');
            }

            if ($paidAmount !== null && $paidAmount !== (int) $trx->total_amount) {
                throw new \UnexpectedValueException('Nominal callback tidak sesuai dengan transaksi.');
            }

            if ($trx->isFinal() || in_array($trx->status, [Transaction::STATUS_PAID, Transaction::STATUS_PROCESSING], true)) {
                return $trx;
            }

            // Expired: jangan proses jadi paid walau gateway telat kirim callback.
            if ($trx->status === Transaction::STATUS_EXPIRED) {
                return $trx;
            }

            $trx->status = Transaction::STATUS_PAID;
            $trx->paid_at = now();
            $trx->payment_payload = array_merge($trx->payment_payload ?? [], ['callback' => $raw]);
            $trx->save();

            $trx->invoice()->update(['status' => 'paid', 'paid_at' => now()]);

            if (($trx->meta['kind'] ?? null) === 'topup' && $trx->user_id) {
                $this->balances->credit(
                    $trx->user,
                    $trx->sell_price,
                    BalanceMutation::TYPE_TOPUP,
                    'Topup via '.$gatewayCode.' '.$trx->invoice_code,
                    $trx->id
                );
                $trx->status = Transaction::STATUS_SUCCESS;
                $trx->save();
            } else {
                DispatchOrderToSupplier::dispatch($trx->id);
            }

            return $trx;
        });
    }

    public function pollGatewayStatus(int $transactionId): ?Transaction
    {
        $trx = Transaction::with('invoice')->find($transactionId);
        if (! $trx || $trx->payment_method === 'balance' || ! $trx->payment_reference) {
            return $trx;
        }

        if ($trx->isFinal() || $trx->status !== Transaction::STATUS_PENDING) {
            return $trx;
        }

        if ($trx->invoice && $trx->invoice->expired_at && $trx->invoice->expired_at->isPast()) {
            return $this->expireTransaction($trx->id);
        }

        // Tetap poll invoice lama walaupun admin menonaktifkan checkout baru.
        $gateway = ProviderFactory::gatewayByCode($trx->payment_gateway_code, activeOnly: false);
        if (! $gateway) {
            return $trx;
        }

        try {
            $result = $gateway->checkStatus($trx->payment_reference);
        } catch (\Throwable $e) {
            report($e);

            return $trx;
        }

        $trx->payment_payload = array_merge($trx->payment_payload ?? [], ['poll_gateway' => $result]);
        $trx->save();

        if (($result['status'] ?? '') === 'paid') {
            return $this->markPaid($trx->payment_reference, $trx->payment_gateway_code, $result['raw'] ?? []);
        }

        if (($result['status'] ?? '') === 'expired') {
            return $this->expireTransaction($trx->id);
        }

        return $trx->fresh();
    }

    public function expireOverdueInvoices(): int
    {
        $ids = Invoice::where('status', 'pending')
            ->whereNotNull('expired_at')
            ->where('expired_at', '<', now())
            ->limit(100)
            ->pluck('transaction_id');

        $count = 0;
        foreach ($ids as $id) {
            $this->expireTransaction($id);
            $count++;
        }

        return $count;
    }

    public function expireTransaction(int $transactionId): ?Transaction
    {
        return DB::transaction(function () use ($transactionId) {
            $trx = Transaction::whereKey($transactionId)->lockForUpdate()->first();
            if (! $trx || $trx->isFinal()) {
                return $trx;
            }

            if (in_array($trx->status, [Transaction::STATUS_PAID, Transaction::STATUS_PROCESSING], true)) {
                return $trx;
            }

            $trx->status = Transaction::STATUS_EXPIRED;
            $trx->save();
            $trx->invoice()->update(['status' => 'expired']);

            return $trx->fresh();
        });
    }
}
