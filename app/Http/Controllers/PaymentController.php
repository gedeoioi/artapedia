<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use App\Services\SupplierStatusSynchronizer;
use Carbon\Carbon;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;

class PaymentController extends Controller
{
    public function show(string $invoice)
    {
        $trx = Transaction::with(['product', 'invoice'])->where('invoice_code', $invoice)->firstOrFail();
        $gatewayPayload = $trx->invoice?->payload ?? $trx->payment_payload ?? [];
        $paymentVia = strtolower((string) data_get($gatewayPayload, 'Data.Via'));
        $paymentChannel = strtolower((string) data_get($gatewayPayload, 'Data.Channel'));
        $isQris = $paymentVia === 'qris' || in_array($paymentChannel, ['qris', 'mpm'], true);
        $qrisImage = null;

        if ($isQris) {
            $qrisPayload = (string) (data_get($gatewayPayload, 'Data.QrString') ?: data_get($gatewayPayload, 'Data.PaymentNo'));
            if ($qrisPayload !== '' && mb_strlen($qrisPayload) <= 4096) {
                try {
                    $qrisImage = (new QRCode(new QROptions([
                        'outputType' => QRCode::OUTPUT_MARKUP_SVG,
                        'outputBase64' => true,
                        'scale' => 6,
                    ])))->render($qrisPayload);
                } catch (\Throwable $e) {
                    report($e);
                }
            }
        }

        $expiresAt = $trx->invoice?->expired_at;
        if ($gatewayExpiry = data_get($gatewayPayload, 'Data.Expired')) {
            try {
                $expiresAt = Carbon::parse($gatewayExpiry, config('app.timezone'));
            } catch (\Throwable) {
                // Gunakan waktu kedaluwarsa invoice sebagai fallback.
            }
        }

        $priceAmount = (int) $trx->sell_price;
        $serviceFee = (int) $trx->admin_fee + (int) $trx->gateway_fee;
        $paymentTotal = (int) $trx->total_amount;

        return view('payment', compact(
            'trx',
            'gatewayPayload',
            'isQris',
            'qrisImage',
            'expiresAt',
            'priceAmount',
            'serviceFee',
            'paymentTotal',
        ));
    }

    public function status(string $invoice, SupplierStatusSynchronizer $synchronizer)
    {
        $trx = Transaction::where('invoice_code', $invoice)->firstOrFail();
        $trx = $synchronizer->refreshIfDue($trx);

        return response()->json([
            'status' => $trx->status,
            'status_label' => $trx->statusLabel(),
            'message' => $trx->statusMessage(),
            'badge' => $trx->statusBadgeClass(),
            'supplier_status' => $trx->supplier_status,
            'paid_at' => $trx->paid_at,
            'processed_at' => $trx->processed_at,
        ])->header('Cache-Control', 'no-store, no-cache, must-revalidate');
    }
}
