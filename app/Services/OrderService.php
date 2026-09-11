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
use App\Payments\IPaymuGateway;
use App\Suppliers\DigiflazzProvider;
use App\Suppliers\TokoVoucherProvider;
use App\Suppliers\VipResellerProvider;
use Carbon\Carbon;
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

            // Validasi format tujuan per tipe produk.
            $target = trim((string) ($data['target_user_id'] ?? ''));
            if (in_array($product->product_type, [Product::TYPE_PULSA, Product::TYPE_DATA], true)) {
                $digits = preg_replace('/\D/', '', $target);
                if (! preg_match('/^(08\d{8,12}|628\d{8,12})$/', $target) && ! preg_match('/^(08\d{8,12}|628\d{8,12})$/', (string) $digits)) {
                    throw new \RuntimeException('Nomor HP tidak valid (cth: 081234567890).');
                }
            }

            $level = $user?->level ?? 'guest';
            $method = $data['gateway_code'] ?? 'balance';
            $quantity = (int) ($data['quantity'] ?? 1);
            $supplier = SupplierConfig::whereKey($product->supplier_config_id)->first();
            $gatewayConfig = $method !== 'balance'
                ? PaymentGatewayConfig::where('code', $method)->where('is_active', true)->firstOrFail()
                : null;
            $ipaymuMethod = null;
            $ipaymuChannel = null;

            if ($method === 'ipaymu') {
                $ipaymuMethod = filled($data['ipaymu_method'] ?? null)
                    ? (string) $data['ipaymu_method']
                    : null;
                $ipaymuChannel = filled($data['ipaymu_channel'] ?? null)
                    ? (string) $data['ipaymu_channel']
                    : null;

                if (! $gatewayConfig->isCheckoutChannelEnabled($ipaymuMethod, $ipaymuChannel)) {
                    throw new \RuntimeException('Channel pembayaran iPaymu tidak aktif. Silakan pilih metode lain.');
                }
            }

            $quote = $this->payments->quote(
                $product,
                $user,
                $method,
                $quantity,
                $ipaymuMethod,
                $ipaymuChannel,
            );

            if ($method === 'ipaymu') {
                $minimumAmount = IPaymuGateway::minimumAmountFor($ipaymuMethod, $ipaymuChannel);
                if ($quote['total'] < $minimumAmount) {
                    $maximumQuantity = $product->maximumOrderQuantity();
                    $minimumQuantity = collect(range(1, $maximumQuantity))->first(
                        fn (int $candidate) => $this->payments->quote(
                            $product,
                            $user,
                            $method,
                            $candidate,
                            $ipaymuMethod,
                            $ipaymuChannel,
                        )['total'] >= $minimumAmount
                    );
                    $quantityHint = $minimumQuantity !== null
                        ? " Tingkatkan jumlah pesanan menjadi minimal {$minimumQuantity}."
                        : '';

                    throw new \RuntimeException('Minimal channel iPaymu ini adalah Rp '.number_format($minimumAmount, 0, ',', '.').'.'.$quantityHint.' Atau gunakan Saldo Member.');
                }
            }

            if ($user && $user->status === 'suspended') {
                throw new \RuntimeException('Akun disuspend.');
            }

            if ($quantity > $product->maximumOrderQuantity()) {
                throw new \RuntimeException('Pembelian lebih dari satu hanya tersedia untuk produk game VIPReseller.');
            }

            $trx = Transaction::create([
                'invoice_code' => $this->payments->invoiceCode(),
                'user_id' => $user?->id,
                'product_id' => $product->id,
                'supplier_config_id' => $product->supplier_config_id,
                'payment_gateway_code' => $method,
                'target_user_id' => $data['target_user_id'],
                'target_zone' => $data['target_zone'] ?? null,
                'nickname' => $data['nickname'] ?? null,
                'quantity' => $quantity,
                'cost_price' => $product->costForLevel($level) * $quantity,
                'sell_price' => $quote['subtotal'],
                'admin_fee' => $quote['admin_fee'],
                'gateway_fee' => $quote['gateway_fee'],
                'total_amount' => $quote['total'],
                'payment_method' => $method,
                'status' => Transaction::STATUS_PENDING,
                'buyer_phone' => $data['buyer_phone'] ?? $user?->phone,
                'buyer_email' => $data['buyer_email'] ?? $user?->email,
            ]);
            $trx->recalculateProfit();
            $trx->save();

            if ($method === 'ipaymu') {
                if (! preg_match('/^(?:\+?62|0)8[0-9]{8,12}$/', (string) $trx->buyer_phone)) {
                    throw new \RuntimeException('Nomor HP wajib diisi dengan format yang valid untuk pembayaran iPaymu.');
                }
                if (! filter_var($trx->buyer_email, FILTER_VALIDATE_EMAIL)) {
                    throw new \RuntimeException('Email wajib diisi dengan format yang valid untuk pembayaran iPaymu.');
                }
            }

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

            $gw = $gatewayConfig;
            $gateway = ProviderFactory::gatewayFor($gw);
            $reference = $this->payments->referenceId('PAY');
            $result = $gateway->createPayment([
                'reference_id' => $reference,
                'amount' => $trx->total_amount,
                'description' => $product->name.' / '.$trx->invoice_code,
                'customer_email' => $trx->buyer_email,
                'customer_phone' => $trx->buyer_phone,
                'customer_name' => $user?->name ?? 'Guest',
                'success_url' => route('payment.show', [
                    'invoice' => $trx->invoice_code,
                    'auto_return' => 1,
                ]),
                'failure_url' => route('checkout.show', $product),
                'payment_method' => $ipaymuMethod ?? null,
                'payment_channel' => $ipaymuChannel ?? null,
            ]);

            if (! ($result['ok'] ?? false)) {
                $message = mb_substr(strip_tags(trim((string) ($result['message'] ?? ''))), 0, 300);

                throw new \RuntimeException($message !== ''
                    ? $gw->name.': '.$message
                    : 'Gateway gagal membuat pembayaran. Silakan coba metode lain.');
            }

            $trx->payment_reference = $reference;
            $trx->payment_payload = array_merge($result['raw'] ?? [], [
                '_checkout_url' => $result['pay_url'] ?? null,
                '_gateway_reference' => $result['gateway_ref'] ?? null,
            ]);
            $trx->save();

            $expiresAt = now()->addHour();
            if ($gatewayExpiry = data_get($trx->payment_payload, 'Data.Expired')) {
                try {
                    $expiresAt = Carbon::parse($gatewayExpiry, config('app.timezone'));
                } catch (\Throwable) {
                    // Pertahankan waktu default jika format gateway tidak valid.
                }
            }

            Invoice::create([
                'transaction_id' => $trx->id,
                'invoice_code' => $trx->invoice_code,
                'gateway_code' => $gw->code,
                'reference_id' => $reference,
                'amount' => $trx->total_amount,
                'status' => 'pending',
                'expired_at' => $expiresAt,
                'payload' => $trx->payment_payload,
            ]);

            return $trx->fresh();
        });
    }

    public function dispatchToSupplier(int $transactionId, ?int $preferredSupplierId = null, bool $manual = false): Transaction
    {
        $trx = Transaction::with(['product', 'supplier'])->findOrFail($transactionId);

        if (! in_array($trx->status, [Transaction::STATUS_PAID, Transaction::STATUS_PROCESSING], true)) {
            return $trx;
        }

        $batchOrders = $trx->payment_payload['supplier_orders'] ?? [];
        if ($trx->quantity > 1 && count($batchOrders) >= $trx->quantity) {
            return $trx;
        }

        if ($trx->quantity > 1 && $batchOrders !== []) {
            $allPreviousSucceeded = collect($batchOrders)->every(
                fn (array $order) => VipResellerProvider::mapStatus((string) ($order['status'] ?? '')) === 'success'
            );

            if (! $allPreviousSucceeded) {
                return $trx;
            }

            return $this->dispatchVipBatch($trx, $trx->supplier, $manual);
        }

        // Pulihkan transaksi batch lama yang sempat hanya membentuk satu order
        // supplier walaupun quantity > 1, tanpa mengulang order pertamanya.
        if ($trx->quantity > 1 && $trx->supplier_trx_id && $trx->supplier?->code === 'vip-reseller') {
            $payload = $trx->payment_payload ?? [];
            $payload['supplier_orders'] ??= [[
                'index' => 1,
                'trxid' => $trx->supplier_trx_id,
                'status' => strtolower((string) ($trx->supplier_status ?: 'waiting')),
            ]];
            $trx->payment_payload = $payload;
            $trx->save();

            return $trx->fresh();
        }

        // Idempotency order tunggal: kalau supplier_trx_id sudah ada, jangan
        // order ulang. Status final ditentukan oleh webhook atau polling.
        if ($trx->supplier_trx_id) {
            return $trx;
        }

        // Atomic claim: hanya 1 worker yang boleh meneruskan ke order HTTP.
        // Mencegah double-order ke supplier saat 2 job/cron berjalan bersamaan.
        $claimed = Transaction::whereKey($trx->id)
            ->whereNull('supplier_trx_id')
            ->whereIn('status', [Transaction::STATUS_PAID, Transaction::STATUS_PROCESSING])
            ->update(['status' => Transaction::STATUS_PROCESSING, 'supplier_status' => 'claiming_order']);

        if (! $claimed) {
            return $trx->fresh();
        }

        $trx = Transaction::with(['product', 'supplier'])->findOrFail($transactionId);

        if ($trx->quantity > 1) {
            $sourceSupplier = SupplierConfig::whereKey($trx->product->supplier_config_id)
                ->where('code', 'vip-reseller')
                ->where('is_active', true)
                ->first();

            if (! $sourceSupplier || $trx->product->product_type !== Product::TYPE_GAME) {
                return $this->markAllSuppliersFailed($trx->id);
            }

            return $this->dispatchVipBatch($trx, $sourceSupplier, $manual);
        }

        $candidates = SupplierConfig::activeOrdered();
        if ($preferredSupplierId) {
            $candidates = $candidates->sortBy(fn ($s) => $s->id === $preferredSupplierId ? 0 : 1)->values();
        }

        foreach ($candidates as $supplier) {
            try {
                $provider = ProviderFactory::supplierFor($supplier);
                $target = $trx->target_user_id;

                $orderOptions = ['trx_id' => $trx->id];

                if ($provider instanceof DigiflazzProvider) {
                    // Digiflazz: ref_id HARUS unik & stabil per transaksi.
                    // Retry / cek status memakai ref_id yang sama -> tidak double charge.
                    // Format: AP-{trx_id} (huruf/angka, aman untuk Digiflazz).
                    $orderOptions['ref_id'] = 'AP-'.$trx->id;
                }

                if ($provider instanceof VipResellerProvider) {
                    // Produk non-game VIPayment dibuat melalui endpoint prepaid.
                    // Simpan pilihan channel secara deterministik dari tipe produk agar
                    // order dan polling status selalu memakai endpoint yang sama.
                    $orderOptions['channel'] = $this->vipChannelForProduct($trx->product);
                    // VIPayment game: zone dikirim TERPISAH via data_zone (dok game-feature).
                    // Jangan digabung "id|zone" — server menolaknya.
                    if ($trx->target_zone) {
                        $orderOptions['zone'] = $trx->target_zone;
                    }
                } elseif ($provider instanceof TokoVoucherProvider) {
                    // TokoVoucher: ref_id stabil + server_id terpisah (dok transaksi/post).
                    $orderOptions['ref_id'] = 'TV-'.$trx->id;
                    if ($trx->target_zone) {
                        $orderOptions['server_id'] = $trx->target_zone;
                    }
                } elseif ($trx->target_zone) {
                    $target = $trx->target_user_id.'|'.$trx->target_zone;
                }

                $res = $provider->order($trx->product->supplier_code, $target, $orderOptions);

                if (! ($res['result'] ?? false)) {
                    $trx->notes = trim(($trx->notes ?? '').' ['.$supplier->code.': '.mb_substr((string) ($res['message'] ?? 'gagal'), 0, 120).']');
                    $trx->save();

                    continue;
                }

                $trx->supplier_config_id = $supplier->id;
                // TokoVoucher: yang dipakai untuk polling & webhook adalah ref_id KITA,
                // bukan trx_id mereka. Provider mengembalikan keduanya.
                $trx->supplier_trx_id = $res['data']['ref_id'] ?? $res['data']['trxid'] ?? $res['trxid'] ?? (string) $trx->id;
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

        return $this->markAllSuppliersFailed($trx->id);
    }

    protected function dispatchVipBatch(Transaction $trx, SupplierConfig $supplier, bool $manual = false): Transaction
    {
        $provider = ProviderFactory::supplierFor($supplier);
        if (! $provider instanceof VipResellerProvider) {
            return $this->markAllSuppliersFailed($trx->id);
        }

        $orders = $trx->payment_payload['supplier_orders'] ?? [];
        $index = count($orders) + 1;
        if ($index <= $trx->quantity) {
            $options = [
                'trx_id' => $trx->id.'-'.$index,
                'channel' => $this->vipChannelForProduct($trx->product),
            ];
            if ($trx->target_zone) {
                $options['zone'] = $trx->target_zone;
            }

            try {
                // Topup game VIPayment tidak mendukung quantity. Buat satu
                // transaksi supplier untuk setiap item yang dibeli.
                $result = $provider->order($trx->product->supplier_code, $trx->target_user_id, $options);
            } catch (\Throwable $e) {
                report($e);
                $result = ['result' => false, 'message' => $e->getMessage()];
            }

            if (! ($result['result'] ?? false) || empty($result['data']['trxid'])) {
                $orders[] = [
                    'index' => $index,
                    'trxid' => null,
                    'status' => 'failed',
                    'message' => (string) ($result['message'] ?? 'Order ditolak VIPayment'),
                ];

            } else {
                $orders[] = [
                    'index' => $index,
                    'trxid' => (string) $result['data']['trxid'],
                    'status' => strtolower((string) ($result['data']['status'] ?? 'waiting')),
                    'sn' => (string) ($result['data']['sn'] ?? ''),
                    'price' => (int) ($result['data']['price'] ?? 0),
                ];
            }
        }

        $accepted = collect($orders)->filter(fn (array $order) => ! empty($order['trxid']));
        if ($accepted->isEmpty()) {
            $trx->payment_payload = array_merge($trx->payment_payload ?? [], ['supplier_orders' => $orders]);
            $trx->save();

            return $this->markAllSuppliersFailed($trx->id);
        }

        $trx->supplier_config_id = $supplier->id;
        $trx->supplier_trx_id = (string) $accepted->first()['trxid'];
        $failedCount = collect($orders)->filter(fn (array $order) => empty($order['trxid']))->count();
        $trx->supplier_status = $failedCount > 0 ? 'partial_failed' : 'waiting';
        $trx->processed_at = now();
        $trx->payment_payload = array_merge($trx->payment_payload ?? [], ['supplier_orders' => $orders]);
        $trx->status = $failedCount > 0 ? Transaction::STATUS_FAILED : Transaction::STATUS_PROCESSING;
        if ($manual) {
            $trx->notes = trim(($trx->notes ?? '').' [batch manual by admin]');
        }
        if ($failedCount > 0) {
            $failureMessage = collect($orders)->whereNull('trxid')->pluck('message')->filter()->join('; ');
            $trx->notes = trim(($trx->notes ?? '').' [Sebagian order supplier gagal dibuat: '.mb_substr($failureMessage, 0, 300).']');
        }
        $trx->save();

        if ($trx->status === Transaction::STATUS_FAILED) {
            $this->refundFailedBatchItems($trx, $failedCount);
        }

        return $trx->fresh();
    }

    public function markAllSuppliersFailed(int $transactionId): Transaction
    {
        return DB::transaction(function () use ($transactionId) {
            $trx = Transaction::with('user')->whereKey($transactionId)->lockForUpdate()->firstOrFail();

            if ($trx->isFinal()) {
                return $trx;
            }

            $trx->supplier_status = 'all_suppliers_failed';
            $trx->status = Transaction::STATUS_FAILED;
            $trx->notes = trim(($trx->notes ?? '').' [Semua supplier gagal menerima order]');
            $trx->save();

            // Saldo sudah didebit saat checkout, tetapi tidak ada supplier yang
            // membentuk order. Kembalikan tepat satu kali.
            $alreadyRefunded = BalanceMutation::where('transaction_id', $trx->id)
                ->where('type', BalanceMutation::TYPE_REFUND)
                ->exists();

            if (! $alreadyRefunded && $trx->payment_method === 'balance' && $trx->user_id && $trx->paid_at) {
                $this->balances->credit(
                    $trx->user,
                    $trx->total_amount,
                    BalanceMutation::TYPE_REFUND,
                    'Refund '.$trx->invoice_code.' semua supplier gagal',
                    $trx->id
                );
            }

            return $trx->fresh();
        });
    }

    public function pollStatus(int $transactionId): Transaction
    {
        $trx = Transaction::with(['supplier', 'product'])->findOrFail($transactionId);

        if ($trx->status === Transaction::STATUS_PROCESSING
            && $trx->quantity > 1
            && empty($trx->payment_payload['supplier_orders'])
            && $trx->supplier_trx_id) {
            // Ubah transaksi lama menjadi batch satu-item terlebih dahulu;
            // item berikutnya baru dibuat setelah status item ini sukses.
            $payload = $trx->payment_payload ?? [];
            $payload['supplier_orders'] = [[
                'index' => 1,
                'trxid' => $trx->supplier_trx_id,
                'status' => strtolower((string) ($trx->supplier_status ?: 'waiting')),
            ]];
            $trx->payment_payload = $payload;
            $trx->save();
            $trx = $trx->fresh(['supplier', 'product']);
        }

        if (! $trx->supplier_trx_id || ! $trx->supplier) {
            return $trx;
        }

        if ($trx->quantity > 1 && count($trx->payment_payload['supplier_orders'] ?? []) > 0) {
            return $this->pollVipBatchStatus($trx);
        }

        $provider = ProviderFactory::supplierFor($trx->supplier);

        // Digiflazz: cek status prepaid = topup ulang dengan ref_id yang sama,
        // butuh buyer_sku_code + customer_no asli.
        // VIPayment: cek ke channel yang sama saat order; data balikan ARRAY.
        // TokoVoucher: order menyimpan ref_id kita ("TV-{id}") di supplier_trx_id,
        // polling memakai ref_id itu (bukan trx_id mereka).
        $statusOptions = [];
        if ($provider instanceof DigiflazzProvider) {
            $statusOptions = [
                'buyer_sku_code' => $trx->product->supplier_code,
                'customer_no' => $trx->target_user_id,
            ];
        } elseif ($provider instanceof VipResellerProvider) {
            $statusOptions = ['channel' => $this->vipChannelForProduct($trx->product)];
        } elseif ($provider instanceof TokoVoucherProvider) {
            $statusOptions = ['ref_id' => $trx->supplier_trx_id];
        }

        $res = $provider->checkStatus($trx->supplier_trx_id, $statusOptions);
        // Bila API status sedang gagal/timeout, pertahankan status supplier
        // terakhir. Jangan menggantinya menjadi string kosong.
        $status = strtolower((string) ($res['data']['status'] ?? $res['status'] ?? $trx->supplier_status ?? 'pending'));

        return DB::transaction(function () use ($trx, $status, $res) {
            $locked = Transaction::whereKey($trx->id)->lockForUpdate()->firstOrFail();

            if ($locked->isFinal()) {
                return $locked;
            }

            $locked->supplier_status = $status;
            $locked->payment_payload = array_merge($locked->payment_payload ?? [], ['poll' => $res]);

            // VIPayment: success | error ; Digiflazz: Sukses | Gagal (+ Pending menyesuaikan).
            if (in_array($status, ['success', 'sukses'], true)) {
                $locked->status = Transaction::STATUS_SUCCESS;
            } elseif (in_array($status, ['failed', 'gagal', 'batal', 'error'], true)) {
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

    protected function pollVipBatchStatus(Transaction $trx): Transaction
    {
        $provider = ProviderFactory::supplierFor($trx->supplier);
        if (! $provider instanceof VipResellerProvider) {
            return $trx;
        }

        $orders = $trx->payment_payload['supplier_orders'] ?? [];
        foreach ($orders as &$order) {
            if (empty($order['trxid']) || in_array(VipResellerProvider::mapStatus((string) ($order['status'] ?? '')), ['success', 'failed'], true)) {
                continue;
            }

            try {
                $result = $provider->checkStatus((string) $order['trxid'], [
                    'channel' => $this->vipChannelForProduct($trx->product),
                ]);
                $order['status'] = strtolower((string) ($result['data']['status'] ?? $result['status'] ?? $order['status'] ?? 'waiting'));
                $order['sn'] = (string) ($result['data']['sn'] ?? $order['sn'] ?? '');
                $order['poll'] = $result['raw'] ?? $result;
            } catch (\Throwable $e) {
                report($e);
            }
        }
        unset($order);

        $updated = $this->saveVipBatchStatuses($trx->id, $orders);

        return $this->dispatchNextVipBatchItemIfReady($updated);
    }

    public function applyVipBatchWebhook(int $transactionId, string $supplierTrxId, array $data): Transaction
    {
        $trx = Transaction::findOrFail($transactionId);
        $orders = $trx->payment_payload['supplier_orders'] ?? [];

        foreach ($orders as &$order) {
            if ((string) ($order['trxid'] ?? '') !== $supplierTrxId) {
                continue;
            }
            $order['status'] = strtolower((string) ($data['status'] ?? $order['status'] ?? 'waiting'));
            $order['sn'] = (string) ($data['note'] ?? $order['sn'] ?? '');
            $order['webhook'] = ['data' => $data, 'at' => now()->toDateTimeString()];
            break;
        }
        unset($order);

        $updated = $this->saveVipBatchStatuses($trx->id, $orders);

        return $this->dispatchNextVipBatchItemIfReady($updated);
    }

    protected function dispatchNextVipBatchItemIfReady(Transaction $trx): Transaction
    {
        $orders = $trx->payment_payload['supplier_orders'] ?? [];
        if ($trx->status !== Transaction::STATUS_PROCESSING || count($orders) >= $trx->quantity) {
            return $trx;
        }

        $allSucceeded = collect($orders)->every(
            fn (array $order) => VipResellerProvider::mapStatus((string) ($order['status'] ?? '')) === 'success'
        );

        return $allSucceeded ? $this->dispatchVipBatch($trx, $trx->supplier) : $trx;
    }

    protected function saveVipBatchStatuses(int $transactionId, array $orders): Transaction
    {
        return DB::transaction(function () use ($transactionId, $orders) {
            $locked = Transaction::whereKey($transactionId)->lockForUpdate()->firstOrFail();

            if ($locked->isFinal()) {
                return $locked;
            }

            $payload = $locked->payment_payload ?? [];
            $payload['supplier_orders'] = $orders;
            $locked->payment_payload = $payload;

            $mapped = collect($orders)->map(fn (array $order) => empty($order['trxid'])
                ? 'failed'
                : VipResellerProvider::mapStatus((string) ($order['status'] ?? 'waiting')));
            $successCount = $mapped->filter(fn (string $status) => $status === 'success')->count();
            $failedCount = $mapped->filter(fn (string $status) => $status === 'failed')->count();
            $pendingCount = $mapped->count() - $successCount - $failedCount;

            if ($successCount === $locked->quantity) {
                $locked->status = Transaction::STATUS_SUCCESS;
                $locked->supplier_status = 'success';
            } elseif ($pendingCount === 0 && $failedCount > 0) {
                $locked->status = Transaction::STATUS_FAILED;
                $locked->supplier_status = $successCount > 0 ? 'partial_failed' : 'failed';
                $locked->notes = trim(($locked->notes ?? '')." [Batch: {$successCount} sukses, {$failedCount} gagal]");
            } else {
                $locked->status = Transaction::STATUS_PROCESSING;
                $locked->supplier_status = "batch_{$successCount}_of_{$locked->quantity}";
            }
            $locked->save();

            if ($locked->status === Transaction::STATUS_FAILED) {
                $this->refundFailedBatchItems($locked, $failedCount);
            }

            return $locked->fresh();
        });
    }

    protected function refundFailedBatchItems(Transaction $trx, int $failedCount): void
    {
        if ($failedCount < 1 || $trx->payment_method !== 'balance' || ! $trx->user_id || ! $trx->paid_at) {
            return;
        }

        $alreadyRefunded = BalanceMutation::where('transaction_id', $trx->id)
            ->where('type', BalanceMutation::TYPE_REFUND)
            ->exists();
        if ($alreadyRefunded) {
            return;
        }

        $unitAmount = intdiv($trx->total_amount, max(1, $trx->quantity));
        $this->balances->credit(
            $trx->user,
            $unitAmount * $failedCount,
            BalanceMutation::TYPE_REFUND,
            'Refund '.$trx->invoice_code." {$failedCount} item supplier gagal",
            $trx->id,
        );
    }

    protected function vipChannelForProduct(Product $product): string
    {
        return in_array($product->product_type, [
            Product::TYPE_PULSA,
            Product::TYPE_DATA,
            Product::TYPE_VOUCHER,
            Product::TYPE_EMONEY,
        ], true) ? 'prepaid' : 'game';
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
