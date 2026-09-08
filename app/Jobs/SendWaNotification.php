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
        $trx = Transaction::find($this->transactionId);
        if (! $trx) {
            return;
        }

        $setting = WaNotificationSetting::where('name', $this->kind)->where('is_active', true)->first();
        if (! $setting || ! $setting->api_url) {
            return;
        }

        try {
            Http::post($setting->api_url, [
                'token' => $setting->api_token,
                'to' => $trx->buyer_phone,
                'message' => str_replace(
                    ['{invoice}', '{status}'],
                    [$trx->invoice_code, $trx->status],
                    $setting->template ?? 'Invoice {invoice}: {status}'
                ),
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
