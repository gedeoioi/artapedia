<?php

namespace App\Services;

use App\Models\WaMessage;
use App\Models\WaNotificationSetting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Pengirim pesan WhatsApp lewat gateway HTTP.
 *
 * Satu-satunya tempat yang berbicara ke gateway. Semua pemanggil (notifikasi
 * transaksi, alert admin, balasan bot) lewat sini supaya normalisasi nomor,
 * bentuk payload, dan pencatatan hasil tidak berbeda-beda per pemanggil.
 *
 * Kontrak gateway yang dipakai (diverifikasi langsung ke endpoint-nya):
 *   POST {base_url}/api/send-message
 *   header X-API-Key
 *   {"to": "62812...", "body": "pesan"} -> 201 {"success": true, ...}
 */
class WaService
{
    /**
     * Koneksi yang dipakai: URL baris setting kalau ada, kalau tidak jatuh ke
     * koneksi bersama (baris aktif pertama yang punya URL).
     *
     * Tanpa fallback ini, pesan yang barisnya hanya berisi template — atau
     * tidak punya baris sama sekali — akan resolve ke null dan diam-diam tidak
     * pernah terkirim.
     */
    public function connection(?string $kind = null): ?array
    {
        $setting = $kind
            ? WaNotificationSetting::where('name', $kind)->first()
            : null;

        $url = $setting?->api_url;
        $token = $setting?->api_token;

        if (! $url || ! $token) {
            $fallback = WaNotificationSetting::query()
                ->whereNotNull('api_url')
                ->where('api_url', '!=', '')
                ->whereNotNull('api_token')
                ->where('api_token', '!=', '')
                ->orderBy('id')
                ->first();

            $url = $url ?: $fallback?->api_url;
            $token = $token ?: $fallback?->api_token;
        }

        if (! $url || ! $token) {
            return null;
        }

        return [
            'url' => $this->resolveUrl($url),
            'token' => $token,
            'setting' => $setting,
        ];
    }

    /**
     * URL yang diisi admin bisa berupa host saja atau sudah lengkap dengan path.
     * Keduanya diterima supaya operator tidak perlu tahu path-nya.
     */
    public function resolveUrl(string $url): string
    {
        $url = rtrim(trim($url), '/');
        $path = (string) config('artapedia.wa.send_path', '/api/send-message');

        if (str_ends_with($url, $path)) {
            return $url;
        }

        return $url.$path;
    }

    public function isConfigured(?string $kind = null): bool
    {
        return $this->connection($kind) !== null;
    }

    /**
     * Normalisasi nomor ke format 62xxxxxxxxxx.
     *
     * Pemanggil mengirim apa saja: 08xx, 62xx, +62xx, dengan spasi, tanda
     * hubung, atau tanda kurung. Dilakukan di satu tempat supaya tidak ada
     * jalur yang mengirim format berbeda.
     */
    public function normalizePhone(?string $phone): string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone) ?? '';

        if ($digits === '') {
            return '';
        }

        // 0 -> 62
        if (str_starts_with($digits, '0')) {
            return '62'.substr($digits, 1);
        }

        // 620 -> 62 (salah ketik umum)
        if (str_starts_with($digits, '620')) {
            return '62'.substr($digits, 3);
        }

        if (str_starts_with($digits, '8')) {
            return '62'.$digits;
        }

        return $digits;
    }

    /**
     * Kirim pesan. Mengembalikan hasil yang bisa dibaca operator, dan selalu
     * mencatat percobaan ke wa_messages (kalau diberi konteks).
     *
     * @return array{ok: bool, http_status: ?int, response: ?string, error: ?string}
     */
    public function send(
        string $phone,
        string $message,
        string $kind = 'trx_status',
        ?int $transactionId = null,
    ): array {
        $to = $this->normalizePhone($phone);

        if ($to === '') {
            return $this->record($transactionId, $kind, '', $message, [
                'ok' => false, 'http_status' => null, 'response' => null,
                'error' => 'Nomor tujuan kosong atau tidak valid.',
            ]);
        }

        $connection = $this->connection($kind);

        if (! $connection) {
            return $this->record($transactionId, $kind, $to, $message, [
                'ok' => false, 'http_status' => null, 'response' => null,
                'error' => 'Gateway WhatsApp belum dikonfigurasi (URL atau token kosong).',
            ]);
        }

        try {
            $response = $this->dispatch($connection, $to, $message);
        } catch (\Throwable $e) {
            // Kegagalan transport dicatat dulu, lalu dilempar ulang supaya
            // antrian mencoba lagi — bukan hilang tanpa jejak.
            $this->record($transactionId, $kind, $to, $message, [
                'ok' => false, 'http_status' => null, 'response' => null,
                'error' => 'Gagal menghubungi gateway: '.$e->getMessage(),
            ]);

            throw $e;
        }

        $body = (string) $response->body();
        $json = $response->json();
        $error = null;

        // HTTP 200/201 BUKAN jaminan terkirim: gateway bisa menjawab
        // {"success": false, "error": "device tidak terhubung"}.
        if ($response->failed()) {
            $error = $this->extractError($json, $body) ?? 'Gateway menolak (HTTP '.$response->status().').';
        } elseif (is_array($json) && array_key_exists('success', $json) && ! $json['success']) {
            $error = $this->extractError($json, $body) ?? 'Gateway melaporkan pengiriman gagal.';
        }

        return $this->record($transactionId, $kind, $to, $message, [
            'ok' => $error === null,
            'http_status' => $response->status(),
            'response' => mb_substr($body, 0, 2000),
            'error' => $error,
        ]);
    }

    protected function dispatch(array $connection, string $to, string $message)
    {
        $timeout = (int) config('artapedia.wa.timeout', 20);
        $connectTimeout = (int) config('artapedia.wa.connect_timeout', 8);
        $header = (string) config('artapedia.wa.token_header', 'X-API-Key');

        $headers = [$header => $connection['token']];

        if (config('artapedia.wa.format') === 'form') {
            $fields = config('artapedia.wa.form_fields');
            $request = Http::withHeaders($headers)
                ->timeout($timeout)
                ->connectTimeout($connectTimeout);

            // Token juga dikirim sebagai field: sebagian gateway form hanya
            // membaca token dari body, bukan header.
            return $request->asForm()->post($connection['url'], [
                $fields['target'] ?? 'target' => $to,
                $fields['message'] ?? 'message' => $message,
                'token' => $connection['token'],
            ]);
        }

        $payload = $this->buildJsonPayload($to, $message, $connection['token']);

        return Http::withHeaders($headers)
            ->timeout($timeout)
            ->connectTimeout($connectTimeout)
            ->withBody($payload, 'application/json')
            ->post($connection['url']);
    }

    /**
     * Bangun payload JSON dari template, dengan nilai yang sudah di-escape.
     *
     * Nilai di-escape lewat json_encode lalu kutip luarnya dibuang, sehingga
     * pesan yang berisi tanda kutip atau baris baru tidak bisa merusak bentuk
     * JSON — cara ini tetap benar untuk teks apa pun.
     *
     * Template yang bukan JSON valid dikirim apa adanya, supaya pesan error
     * dari gateway terlihat di log alih-alih tertutup oleh penggantian diam-diam.
     */
    protected function buildJsonPayload(string $to, string $message, string $token): string
    {
        $template = (string) config('artapedia.wa.payload_template');

        $escaped = [
            '{{phone}}' => $this->jsonEscape($to),
            '{{message}}' => $this->jsonEscape($message),
            '{{token}}' => $this->jsonEscape($token),
            '{{sender}}' => $this->jsonEscape((string) config('artapedia.wa.sender', '')),
        ];

        $payload = str_replace(array_keys($escaped), array_values($escaped), $template);

        if (json_decode($payload, true) === null && json_last_error() !== JSON_ERROR_NONE) {
            Log::warning('Payload WA bukan JSON valid, dikirim apa adanya.', [
                'payload' => mb_substr($payload, 0, 500),
                'error' => json_last_error_msg(),
            ]);
        }

        return $payload;
    }

    /** Escape nilai untuk disisipkan ke dalam string JSON. */
    protected function jsonEscape(string $value): string
    {
        $encoded = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if ($encoded === false) {
            // Nilai dengan byte rusak: buang byte-nya dulu supaya JSON tetap valid.
            $clean = mb_convert_encoding($value, 'UTF-8', 'UTF-8');
            $encoded = json_encode($clean, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '""';
        }

        return substr($encoded, 1, -1);
    }

    protected function extractError(mixed $json, string $body): ?string
    {
        if (is_array($json)) {
            foreach (['error', 'message', 'reason', 'detail'] as $key) {
                $value = $json[$key] ?? null;
                if (is_string($value) && $value !== '') {
                    return $value;
                }
                if (is_array($value) && isset($value['message']) && is_string($value['message'])) {
                    return $value['message'];
                }
            }
        }

        $trimmed = trim(strip_tags($body));

        return $trimmed !== '' ? mb_substr($trimmed, 0, 300) : null;
    }

    /**
     * Catat percobaan ke wa_messages. Satu baris per (transaksi, jenis pesan):
     * percobaan ulang menambah attempts, bukan menumpuk baris baru.
     */
    protected function record(?int $transactionId, string $kind, string $to, string $message, array $result): array
    {
        try {
            $log = WaMessage::firstOrNew([
                'transaction_id' => $transactionId,
                'kind' => $kind,
            ]);

            $log->forceFill([
                'phone' => $to !== '' ? $to : (string) $log->phone,
                'message' => $message,
                'status' => $result['ok'] ? WaMessage::STATUS_SENT : WaMessage::STATUS_FAILED,
                'attempts' => ((int) $log->attempts) + 1,
                'http_status' => $result['http_status'],
                'response' => $result['response'],
                'error' => $result['error'],
                'sent_at' => $result['ok'] ? now() : $log->sent_at,
            ])->save();
        } catch (\Throwable $e) {
            // Pencatatan tidak boleh menggagalkan pengiriman itu sendiri.
            Log::warning('Gagal mencatat log WA: '.$e->getMessage());
        }

        if (! $result['ok']) {
            Log::warning('Pengiriman WA gagal', [
                'to' => $to, 'kind' => $kind, 'error' => $result['error'],
            ]);
        }

        return $result;
    }
}
