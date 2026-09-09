<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\BalanceMutation;
use App\Models\SupplierConfig;
use App\Models\Transaction;
use App\Services\BalanceService;
use App\Services\OrderService;
use App\Services\ProviderFactory;
use App\Suppliers\DigiflazzProvider;
use App\Suppliers\TokoVoucherProvider;
use App\Suppliers\VipResellerProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Webhook supplier (H2H inbound). Beda dengan /webhook/payment/* (uang masuk).
 *
 * Digiflazz: POST JSON {data: {ref_id, status, sn, rc, ...}}
 * Header: X-Hub-Signature: sha1=HMAC-SHA1(raw body, webhook secret),
 * X-Digiflazz-Event: create|update, User-Agent: Digiflazz-Hookshot.
 * Secret diatur di Digiflazz: Atur Koneksi > API > Webhook.
 *
 * VIPayment: POST JSON {data: {trxid, data, zone, service, status, note, price}}
 * Header: X-Client-Signature = md5(API ID + API KEY). Whitelist IP 178.248.73.218.
 */
class SupplierWebhookController extends Controller
{
    public function digiflazz(Request $request, OrderService $orders)
    {
        $config = SupplierConfig::where('code', 'digiflazz')->first();

        if (! $config) {
            return response()->json(['ok' => false, 'message' => 'Supplier digiflazz belum dikonfigurasi'], 404);
        }

        $provider = ProviderFactory::supplierFor($config);

        if (! $provider instanceof DigiflazzProvider) {
            return response()->json(['ok' => false, 'message' => 'Provider mismatch'], 500);
        }

        $raw = $request->getContent();
        $signature = $request->header('X-Hub-Signature');

        if (! $provider->verifyWebhook($raw, $signature)) {
            AuditLog::record('supplier.webhook.invalid_signature', $config, [], [
                'event' => $request->header('X-Digiflazz-Event'),
            ]);

            return response()->json(['ok' => false, 'reason' => 'invalid_signature'], 401);
        }

        $data = $request->input('data', []);
        $refId = (string) ($data['ref_id'] ?? '');
        $status = (string) ($data['status'] ?? '');

        if ($refId === '') {
            return response()->json(['ok' => false, 'reason' => 'missing_ref_id'], 400);
        }

        // Idempotency: cari by ref_id (= supplier_trx_id). Double webhook aman
        // karena pollStatus()/finalisasi memakai lock + cek isFinal.
        $trx = Transaction::where('supplier_trx_id', $refId)->first();

        if (! $trx) {
            return response()->json(['ok' => true, 'ignored' => true]);
        }

        DB::transaction(function () use ($trx, $data, $status) {
            $locked = Transaction::whereKey($trx->id)->lockForUpdate()->firstOrFail();

            if ($locked->isFinal()) {
                return;
            }

            $locked->supplier_status = strtolower($status);
            $locked->payment_payload = array_merge($locked->payment_payload ?? [], [
                'webhook_digiflazz' => [
                    'event' => request()->header('X-Digiflazz-Event'),
                    'data' => $data,
                    'at' => now()->toDateTimeString(),
                ],
            ]);

            if (! empty($data['sn'])) {
                $locked->notes = trim(($locked->notes ?? '').' [SN: '.$data['sn'].']');
            }

            $mapped = DigiflazzProvider::mapStatus($status);

            if ($mapped === 'success') {
                $locked->status = Transaction::STATUS_SUCCESS;
            } elseif ($mapped === 'failed') {
                $locked->status = Transaction::STATUS_FAILED;
                if ($locked->payment_method === 'balance' && $locked->user_id && $locked->paid_at) {
                    app(BalanceService::class)->credit(
                        $locked->user,
                        $locked->total_amount,
                        BalanceMutation::TYPE_REFUND,
                        'Refund '.$locked->invoice_code.' Digiflazz gagal (webhook)',
                        $locked->id
                    );
                }
            }
            $locked->save();
        });

        AuditLog::record('supplier.webhook.digiflazz', $config, [], ['ref_id' => $refId, 'status' => $status]);

        return response()->json(['ok' => true]);
    }

    public function vipReseller(Request $request, OrderService $orders)
    {
        $config = SupplierConfig::where('code', 'vip-reseller')->first();

        if (! $config) {
            return response()->json(['ok' => false, 'message' => 'Supplier vip-reseller belum dikonfigurasi'], 404);
        }

        $provider = ProviderFactory::supplierFor($config);

        if (! $provider instanceof VipResellerProvider) {
            return response()->json(['ok' => false, 'message' => 'Provider mismatch'], 500);
        }

        if (! $provider->verifyWebhook($request->header('X-Client-Signature'))) {
            AuditLog::record('supplier.webhook.invalid_signature', $config, [], ['supplier' => 'vip-reseller']);

            return response()->json(['ok' => false, 'reason' => 'invalid_signature'], 401);
        }

        $data = $request->input('data', $request->all());
        $trxid = (string) ($data['trxid'] ?? '');
        $status = (string) ($data['status'] ?? '');

        if ($trxid === '') {
            return response()->json(['ok' => false, 'reason' => 'missing_trxid'], 400);
        }

        $trx = Transaction::where('supplier_trx_id', $trxid)->first();

        if (! $trx) {
            // Batch quantity menyimpan ID kedua dan seterusnya di payload.
            $trx = Transaction::where('supplier_config_id', $config->id)
                ->where('quantity', '>', 1)
                ->where('created_at', '>', now()->subDays(7))
                ->latest('id')
                ->limit(200)
                ->get()
                ->first(fn (Transaction $candidate) => collect($candidate->payment_payload['supplier_orders'] ?? [])
                    ->contains(fn (array $order) => (string) ($order['trxid'] ?? '') === $trxid));
        }

        if (! $trx) {
            AuditLog::record('supplier.webhook.vip-reseller.unmatched', $config, [], [
                'trxid' => $trxid,
                'status' => $status,
            ]);

            return response()->json([
                'ok' => true,
                'matched' => false,
                'ignored' => true,
                'reason' => 'transaction_not_found',
                'trxid' => $trxid,
                'received_status' => strtolower($status),
            ]);
        }

        if (count($trx->payment_payload['supplier_orders'] ?? []) > 1) {
            $trx = $orders->applyVipBatchWebhook($trx->id, $trxid, $data);

            AuditLog::record('supplier.webhook.vip-reseller', $config, [], [
                'trxid' => $trxid,
                'status' => $status,
                'batch' => true,
            ]);

            return response()->json([
                'ok' => true,
                'matched' => true,
                'trxid' => $trxid,
                'received_status' => strtolower($status),
                'transaction_status' => $trx->status,
                'supplier_status' => $trx->supplier_status,
            ]);
        }

        DB::transaction(function () use ($trx, $data, $status) {
            $locked = Transaction::whereKey($trx->id)->lockForUpdate()->firstOrFail();

            if ($locked->isFinal()) {
                return;
            }

            $locked->supplier_status = strtolower($status);
            $locked->payment_payload = array_merge($locked->payment_payload ?? [], [
                'webhook_vip' => ['data' => $data, 'at' => now()->toDateTimeString()],
            ]);

            if (! empty($data['note'])) {
                $locked->notes = trim(($locked->notes ?? '').' [VIP: '.$data['note'].']');
            }

            $mapped = VipResellerProvider::mapStatus($status);

            if ($mapped === 'success') {
                $locked->status = Transaction::STATUS_SUCCESS;
            } elseif ($mapped === 'failed') {
                $locked->status = Transaction::STATUS_FAILED;
                if ($locked->payment_method === 'balance' && $locked->user_id && $locked->paid_at) {
                    app(BalanceService::class)->credit(
                        $locked->user,
                        $locked->total_amount,
                        BalanceMutation::TYPE_REFUND,
                        'Refund '.$locked->invoice_code.' VIPayment gagal (webhook)',
                        $locked->id
                    );
                }
            }
            $locked->save();
        });

        AuditLog::record('supplier.webhook.vip-reseller', $config, [], ['trxid' => $trxid, 'status' => $status]);

        $trx->refresh();

        return response()->json([
            'ok' => true,
            'matched' => true,
            'trxid' => $trxid,
            'received_status' => strtolower($status),
            'transaction_status' => $trx->status,
            'supplier_status' => $trx->supplier_status,
        ]);
    }

    public function tokoVoucher(Request $request)
    {
        $config = SupplierConfig::where('code', 'toko-voucher')->first();

        if (! $config) {
            return response()->json(['ok' => false, 'message' => 'Supplier toko-voucher belum dikonfigurasi'], 404);
        }

        $provider = ProviderFactory::supplierFor($config);

        if (! $provider instanceof TokoVoucherProvider) {
            return response()->json(['ok' => false, 'message' => 'Provider mismatch'], 500);
        }

        $refId = (string) ($request->input('ref_id') ?? '');
        $status = (string) ($request->input('status') ?? '');

        if ($refId === '') {
            return response()->json(['ok' => false, 'reason' => 'missing_ref_id'], 400);
        }

        if (! $provider->verifyWebhook($request->header('X-TokoVoucher-Authorization'), $refId)) {
            AuditLog::record('supplier.webhook.invalid_signature', $config, [], ['supplier' => 'toko-voucher']);

            return response()->json(['ok' => false, 'reason' => 'invalid_signature'], 401);
        }

        $trx = Transaction::where('supplier_trx_id', $refId)->first();

        if (! $trx) {
            return response()->json(['ok' => true, 'ignored' => true]);
        }

        DB::transaction(function () use ($trx, $request, $status) {
            $locked = Transaction::whereKey($trx->id)->lockForUpdate()->firstOrFail();

            if ($locked->isFinal()) {
                return;
            }

            $locked->supplier_status = strtolower($status);
            $locked->payment_payload = array_merge($locked->payment_payload ?? [], [
                'webhook_toko' => [
                    'status' => $status,
                    'sn' => $request->input('sn'),
                    'trx_id' => $request->input('trx_id'),
                    'price' => $request->input('price'),
                    'message' => $request->input('message'),
                    'at' => now()->toDateTimeString(),
                ],
            ]);

            if ($request->input('sn')) {
                $locked->notes = trim(($locked->notes ?? '').' [SN: '.$request->input('sn').']');
            }

            $mapped = TokoVoucherProvider::mapStatus($status);

            if ($mapped === 'success') {
                $locked->status = Transaction::STATUS_SUCCESS;
            } elseif ($mapped === 'failed') {
                $locked->status = Transaction::STATUS_FAILED;
                if ($locked->payment_method === 'balance' && $locked->user_id && $locked->paid_at) {
                    app(BalanceService::class)->credit(
                        $locked->user,
                        $locked->total_amount,
                        BalanceMutation::TYPE_REFUND,
                        'Refund '.$locked->invoice_code.' TokoVoucher gagal (webhook)',
                        $locked->id
                    );
                }
            }
            $locked->save();
        });

        AuditLog::record('supplier.webhook.toko-voucher', $config, [], ['ref_id' => $refId, 'status' => $status]);

        return response()->json(['ok' => true]);
    }
}
