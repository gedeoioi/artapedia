<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\SupplierConfig;
use App\Services\OrderService;
use App\Services\ProviderFactory;
use App\Suppliers\DigiflazzProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Webhook supplier (H2H inbound). Beda dengan /webhook/payment/* (uang masuk).
 *
 * Digiflazz: POST JSON {data: {ref_id, status, sn, rc, ...}}
 * Header: X-Hub-Signature: sha1=HMAC-SHA1(raw body, webhook secret),
 * X-Digiflazz-Event: create|update, User-Agent: Digiflazz-Hookshot.
 * Secret diatur di Digiflazz: Atur Koneksi > API > Webhook.
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
        $trx = \App\Models\Transaction::where('supplier_trx_id', $refId)->first();

        if (! $trx) {
            return response()->json(['ok' => true, 'ignored' => true]);
        }

        DB::transaction(function () use ($trx, $data, $status) {
            $locked = \App\Models\Transaction::whereKey($trx->id)->lockForUpdate()->firstOrFail();

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
                $locked->status = \App\Models\Transaction::STATUS_SUCCESS;
            } elseif ($mapped === 'failed') {
                $locked->status = \App\Models\Transaction::STATUS_FAILED;
                if ($locked->payment_method === 'balance' && $locked->user_id && $locked->paid_at) {
                    app(\App\Services\BalanceService::class)->credit(
                        $locked->user,
                        $locked->total_amount,
                        \App\Models\BalanceMutation::TYPE_REFUND,
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
}
