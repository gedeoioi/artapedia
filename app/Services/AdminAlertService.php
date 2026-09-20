<?php

namespace App\Services;

use App\Models\User;
use App\Models\WaNotificationSetting;

/**
 * Pengirim pesan operasional ke admin (alert rekonsiliasi, ringkasan sistem).
 *
 * Dipisahkan dari notifikasi pembeli supaya alert admin tetap bisa dikirim
 * walaupun template notifikasi transaksi dimatikan, dan supaya pesan tidak
 * bergantung pada data transaksi (alert rekonsiliasi tidak punya transaksi).
 */
class AdminAlertService
{
    public const CHANNEL = 'admin_alert';

    public function __construct(protected WaService $wa) {}

    /**
     * @return array<int, string> nomor WhatsApp admin dalam format internasional
     */
    public function recipients(): array
    {
        $setting = WaNotificationSetting::where('name', self::CHANNEL)->first();

        $numbers = collect(explode(',', (string) ($setting->recipient ?? '')))
            ->map(fn (string $number): string => $this->normalize($number));

        $adminNumbers = User::query()
            ->where('level', 'admin')
            ->orWhereHas('roles', fn ($query) => $query->where('name', 'admin'))
            ->pluck('whatsapp')
            ->map(fn (?string $number): string => $this->normalize((string) $number));

        return $numbers
            ->merge($adminNumbers)
            ->filter(fn (string $number): bool => $number !== '')
            ->unique()
            ->values()
            ->all();
    }

    public function send(string $message, string $channel = self::CHANNEL): bool
    {
        $setting = WaNotificationSetting::where('name', $channel)->where('is_active', true)->first();

        if (! $setting || ! $setting->api_url) {
            return false;
        }

        $recipients = $this->recipients();
        if ($recipients === []) {
            return false;
        }

        $sent = false;
        foreach ($recipients as $recipient) {
            try {
                // Lewat WaService supaya bentuk payload, header token, dan
                // pencatatan hasil sama dengan notifikasi pembeli. Sebelumnya
                // pengiriman di sini punya bentuk sendiri yang tidak cocok
                // dengan kontrak gateway.
                $result = $this->wa->send($recipient, $message, $channel);

                if ($result['ok']) {
                    $sent = true;
                }
            } catch (\Throwable $e) {
                // Transport gagal: catat, jangan hentikan pengiriman ke penerima lain.
                report($e);
            }
        }

        if ($sent) {
            $setting->forceFill(['last_sent_at' => now()])->save();
        }

        return $sent;
    }

    /**
     * Samakan format nomor agar satu admin tidak menerima pesan dua kali dan
     * nomor lokal (08xx) tetap sampai ke gateway yang butuh format 62xx.
     *
     * Normalisasi sebenarnya ada di WaService (dipakai bersama); method ini
     * dipertahankan supaya pemanggil lama tidak perlu diubah.
     */
    public function normalize(string $number): string
    {
        return $this->wa->normalizePhone($number);
    }
}
