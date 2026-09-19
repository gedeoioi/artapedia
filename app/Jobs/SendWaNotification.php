<?php

namespace App\Jobs;

use App\Models\Transaction;
use App\Models\WaNotificationSetting;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;

class SendWaNotification implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $transactionId, public string $kind = 'status') {}

    public function handle(): void
    {
        $trx = Transaction::with('product')->find($this->transactionId);
        if (! $trx) {
            return;
        }

        $setting = WaNotificationSetting::where('name', $this->kind)->where('is_active', true)->first();
        if (! $setting || ! $setting->api_url) {
            return;
        }

        $template = $setting->template ?? 'Invoice {invoice}: {status}';

        try {
            Http::post($setting->api_url, [
                'token' => $setting->api_token,
                'to' => $trx->buyer_phone,
                'message' => str_replace(
                    ['{invoice}', '{status}', '{link}', '{nama}', '{produk}', '{tujuan}', '{nominal}', '{tanggal}'],
                    [
                        $trx->invoice_code,
                        $trx->statusLabel(),
                        route('invoice.show', ['code' => $trx->invoice_code]),
                        $trx->user?->name ?? 'Pelanggan',
                        $trx->product?->name ?? 'Topup Saldo',
                        $trx->target_user_id,
                        'Rp '.number_format((int) $trx->total_amount, 0, ',', '.'),
                        $trx->created_at?->format('d/m/Y H:i') ?? '-',
                    ],
                    $template,
                ),
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
