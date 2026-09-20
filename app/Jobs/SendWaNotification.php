<?php

namespace App\Jobs;

use App\Models\Transaction;
use App\Models\WaNotificationSetting;
use App\Services\WaService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Kirim notifikasi WhatsApp status transaksi ke pembeli.
 *
 * Job ini sebelumnya didefinisikan tapi TIDAK PERNAH di-dispatch dari mana pun,
 * jadi tidak ada satu pun notifikasi yang terkirim. Sekarang dipanggil dari
 * Transaction::notifyBuyer() pada setiap transisi ke status final.
 */
class SendWaNotification implements ShouldQueue
{
    use Queueable;

    public int $tries;

    /** @var array<int, int> */
    public array $backoff;

    public function __construct(
        public int $transactionId,
        public string $kind = 'trx_status',
    ) {
        $this->tries = max(1, (int) config('artapedia.wa.tries', 3));
        $this->backoff = (array) config('artapedia.wa.backoff', [10, 60, 300]);
    }

    public function handle(WaService $wa): void
    {
        $trx = Transaction::with('product')->find($this->transactionId);

        if (! $trx) {
            return;
        }

        $setting = WaNotificationSetting::where('name', $this->kind)->first();

        // Baris setting ada tapi dimatikan -> tidak ada yang dikirim. Kalau
        // barisnya belum ada sama sekali, template default tetap dipakai supaya
        // notifikasi dasar tetap jalan pada pemasangan baru.
        if ($setting && ! $setting->is_active) {
            return;
        }

        $phone = $trx->buyer_phone;

        if (! $phone) {
            return;
        }

        $wa->send(
            $phone,
            $this->render($trx, $setting?->template),
            $this->kind,
            $trx->id,
        );
    }

    /**
     * Isi template. Placeholder yang tidak dikenal dibiarkan apa adanya supaya
     * salah ketik (mis. {nomnal}) terlihat saat menelusuri log, bukan berubah
     * jadi teks kosong tanpa jejak.
     */
    protected function render(Transaction $trx, ?string $template): string
    {
        $template = $template ?: "ArtaPedia: Invoice {invoice} status {status}.\nCek di {link}";

        $map = [
            '{invoice}' => $trx->invoice_code,
            '{status}' => $trx->statusLabel(),
            '{link}' => route('invoice.show', ['code' => $trx->invoice_code]),
            '{link_invoice}' => route('invoice.show', ['code' => $trx->invoice_code]),
            '{nama}' => $trx->user?->name ?? 'Pelanggan',
            '{produk}' => $trx->product?->name ?? 'Topup Saldo',
            '{tujuan}' => (string) $trx->target_user_id,
            '{nominal}' => 'Rp '.number_format((int) $trx->total_amount, 0, ',', '.'),
            '{tanggal}' => $trx->created_at?->format('d/m/Y H:i') ?? '-',
            '{situs}' => (string) config('app.name', 'ArtaPedia'),
        ];

        return str_replace(array_keys($map), array_values($map), $template);
    }
}
