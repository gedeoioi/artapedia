<?php

namespace App\Payments;

use Illuminate\Support\Facades\Http;

/**
 * Tripay — closed payment.
 *
 * Signature pembuatan transaksi: HMAC-SHA256(merchantCode . merchantRef . amount)
 * dengan kunci private key. Callback diverifikasi lewat header
 * X-Callback-Signature = HMAC-SHA256(body mentah, private key), dengan
 * X-Callback-Event = payment_status sebagai penanda sumber.
 *
 * Referensi: https://tripay.co.id/developer?tab=transaction-create
 */
class TripayGateway extends BasePaymentGateway
{
    /** Channel closed payment yang dipetakan ke grup di checkout. */
    public const CHANNELS = [
        'QRIS' => ['label' => 'QRIS', 'group' => 'qris'],
        'BRIVA' => ['label' => 'BRI Virtual Account', 'group' => 'va'],
        'BNIVA' => ['label' => 'BNI Virtual Account', 'group' => 'va'],
        'BCAVA' => ['label' => 'BCA Virtual Account', 'group' => 'va'],
        'MANDIRIVA' => ['label' => 'Mandiri Virtual Account', 'group' => 'va'],
        'PERMATAVA' => ['label' => 'Permata Virtual Account', 'group' => 'va'],
        'CIMBVA' => ['label' => 'CIMB Niaga Virtual Account', 'group' => 'va'],
        'MUAMALATVA' => ['label' => 'Muamalat Virtual Account', 'group' => 'va'],
        'BSIVA' => ['label' => 'BSI Virtual Account', 'group' => 'va'],
        'DANAMONVA' => ['label' => 'Danamon Virtual Account', 'group' => 'va'],
        'SAMPOERNAVA' => ['label' => 'Sampoerna Virtual Account', 'group' => 'va'],
        'ALFAMART' => ['label' => 'Alfamart', 'group' => 'cstore'],
        'INDOMARET' => ['label' => 'Indomaret', 'group' => 'cstore'],
        'ALFAMIDI' => ['label' => 'Alfamidi', 'group' => 'cstore'],
        'OVO' => ['label' => 'OVO', 'group' => 'ewallet'],
        'DANA' => ['label' => 'DANA', 'group' => 'ewallet'],
        'SHOPEEPAY' => ['label' => 'ShopeePay', 'group' => 'ewallet'],
    ];

    /**
     * Channel dengan biaya flat tinggi tidak masuk akal untuk tiket kecil.
     * Ambang ini dipakai checkout untuk menyembunyikan/menolak nominal kecil.
     */
    public const MINIMUM_AMOUNT = 10_000;

    public function code(): string
    {
        return 'tripay';
    }

    protected function baseUrl(): string
    {
        return $this->credentials['base_url']
            ?? ($this->sandbox ? 'https://tripay.co.id/api-sandbox' : 'https://tripay.co.id/api');
    }

    protected function apiKey(): string
    {
        return trim((string) ($this->credentials['api_key'] ?? ''));
    }

    protected function privateKey(): string
    {
        return trim((string) ($this->credentials['private_key'] ?? ''));
    }

    protected function merchantCode(): string
    {
        return trim((string) ($this->credentials['merchant_code'] ?? ''));
    }

    public static function supportsChannel(?string $channel): bool
    {
        return $channel !== null && isset(self::CHANNELS[$channel]);
    }

    public static function groupForChannel(?string $channel): ?string
    {
        return $channel !== null ? (self::CHANNELS[$channel]['group'] ?? null) : null;
    }

    /**
     * Daftar channel untuk form checkout, dikelompokkan dengan label Indonesia.
     *
     * @return array<int, array{code: string, label: string, group: string, group_label: string}>
     */
    public static function checkoutChannels(): array
    {
        $groupLabels = [
            'qris' => 'QRIS',
            'va' => 'Virtual Account',
            'ewallet' => 'E-Wallet',
            'cstore' => 'Gerai Retail',
        ];

        $out = [];
        foreach (self::CHANNELS as $code => $meta) {
            $out[] = [
                'code' => $code,
                'label' => $meta['label'],
                'group' => $meta['group'],
                'group_label' => $groupLabels[$meta['group']] ?? $meta['group'],
            ];
        }

        return $out;
    }

    /**
     * Signature request transaksi: HMAC-SHA256 dari merchantCode+merchantRef+amount.
     */
    public function signature(string $merchantRef, int $amount): string
    {
        return hash_hmac('sha256', $this->merchantCode().$merchantRef.$amount, $this->privateKey());
    }

    /**
     * Verifikasi callback. Body MENTAH yang ditandatangani, bukan hasil
     * json_decode + encode ulang.
     */
    public function verifyCallback(string $rawBody, string $signatureHeader): bool
    {
        $privateKey = $this->privateKey();

        if ($privateKey === '' || $signatureHeader === '') {
            return false;
        }

        return hash_equals(hash_hmac('sha256', $rawBody, $privateKey), $signatureHeader);
    }

    public function createPayment(array $params): array
    {
        $merchantRef = (string) $params['reference_id'];
        $amount = (int) $params['amount'];
        $channel = (string) ($params['payment_channel'] ?? '');

        if ($this->apiKey() === '' || $this->privateKey() === '' || $this->merchantCode() === '') {
            return [
                'ok' => false,
                'reference_id' => $merchantRef,
                'gateway_ref' => null,
                'pay_url' => null,
                'message' => 'API Key, Private Key, atau Merchant Code Tripay belum dikonfigurasi.',
                'raw' => [],
            ];
        }

        if (! self::supportsChannel($channel)) {
            return [
                'ok' => false,
                'reference_id' => $merchantRef,
                'gateway_ref' => null,
                'pay_url' => null,
                'message' => 'Channel Tripay tidak dikenal: '.$channel.'.',
                'raw' => [],
            ];
        }

        $expiredAt = (int) ($params['expired_at'] ?? now()->addHour()->timestamp);

        $body = [
            'method' => $channel,
            'merchant_ref' => $merchantRef,
            'amount' => $amount,
            'customer_name' => (string) ($params['customer_name'] ?? 'ArtaPedia Buyer'),
            'customer_email' => (string) ($params['customer_email'] ?? ''),
            'customer_phone' => (string) ($params['customer_phone'] ?? ''),
            'order_items' => [[
                'sku' => (string) ($params['sku'] ?? $merchantRef),
                'name' => mb_substr((string) ($params['description'] ?? $merchantRef), 0, 100),
                'price' => $amount,
                'quantity' => 1,
            ]],
            'callback_url' => $this->callbackBase('tripay'),
            'return_url' => $params['success_url'] ?? config('app.url'),
            'expired_time' => $expiredAt,
            'signature' => $this->signature($merchantRef, $amount),
        ];

        $res = Http::acceptJson()
            ->withToken($this->apiKey())
            ->timeout(30)
            ->post($this->baseUrl().'/transaction/create', $body);

        $json = $res->json() ?? [];
        $data = $json['data'] ?? [];

        $ok = $res->successful()
            && ($json['success'] ?? false) === true
            && filled($data['reference'] ?? null);

        return [
            'ok' => $ok,
            'reference_id' => $merchantRef,
            'gateway_ref' => $data['reference'] ?? null,
            'pay_url' => $data['checkout_url'] ?? null,
            'message' => (string) ($json['message'] ?? 'Tripay menolak pembuatan pembayaran.'),
            'raw' => $json,
        ];
    }

    public function handleCallback(array $payload, array $headers = [], ?string $rawBody = null): array
    {
        $signature = $this->headerValue($headers, 'x-callback-signature');
        $event = $this->headerValue($headers, 'x-callback-event');

        // Tripay mengirim event lain (mis. payment_status hanya untuk perubahan
        // status bayar). Abaikan event yang bukan status pembayaran, tapi tetap
        // balas OK supaya gateway tidak mengulang kirim tanpa henti.
        if ($event !== '' && $event !== 'payment_status') {
            return [
                'ok' => true,
                'status' => 'ignored',
                'reference_id' => $payload['merchant_ref'] ?? null,
                'raw' => $payload,
            ];
        }

        // Body mentah wajib ada. Kalau kosong, jangan pernah menganggap valid.
        if ($rawBody === null || $rawBody === '') {
            return ['ok' => false, 'status' => 'missing_body', 'reference_id' => $payload['merchant_ref'] ?? null];
        }

        if (! $this->verifyCallback($rawBody, $signature)) {
            return ['ok' => false, 'status' => 'invalid_signature', 'reference_id' => $payload['merchant_ref'] ?? null];
        }

        $status = strtoupper((string) ($payload['status'] ?? ''));

        // Hanya PAID yang dianggap lunas. UNPAID/EXPIRED/REFUND/FAILED tidak
        // boleh mengkredit saldo.
        $mapped = match ($status) {
            'PAID' => 'paid',
            'EXPIRED' => 'expired',
            default => 'pending',
        };

        return [
            'ok' => true,
            'reference_id' => $payload['merchant_ref'] ?? null,
            'amount' => isset($payload['total_amount'])
                ? (int) $payload['total_amount']
                : (isset($payload['amount']) ? (int) $payload['amount'] : null),
            'status' => $mapped,
            'raw' => $payload,
        ];
    }

    public function checkStatus(string $referenceId): array
    {
        $res = Http::acceptJson()
            ->withToken($this->apiKey())
            ->timeout(30)
            ->get($this->baseUrl().'/transaction/detail', ['reference' => $referenceId]);

        $json = $res->json() ?? [];
        $data = $json['data'] ?? [];
        $status = strtoupper((string) ($data['status'] ?? ''));

        return [
            'ok' => $res->successful() && ($json['success'] ?? false) === true,
            'reference_id' => $referenceId,
            'status' => match ($status) {
                'PAID' => 'paid',
                'EXPIRED', 'FAILED', 'REFUND' => 'expired',
                default => 'pending',
            },
            'raw' => $json,
        ];
    }

    /**
     * Channel aktif milik merchant, diambil dari API Tripay.
     *
     * @return array<int, array{code: string, name: string, group: ?string, fee_flat: int, fee_percent: float}>
     */
    public function activePaymentChannels(): array
    {
        if ($this->apiKey() === '') {
            throw new \RuntimeException('API Key Tripay belum dikonfigurasi.');
        }

        $res = Http::acceptJson()->withToken($this->apiKey())->timeout(30)
            ->get($this->baseUrl().'/merchant/payment-channel');

        $json = $res->json() ?? [];

        if (! $res->successful() || ($json['success'] ?? false) !== true) {
            throw new \RuntimeException((string) ($json['message'] ?? 'Daftar channel Tripay gagal dimuat.'));
        }

        $out = [];
        foreach ($json['data'] ?? [] as $channel) {
            $code = strtoupper((string) ($channel['code'] ?? ''));
            if (! self::supportsChannel($code)) {
                continue;
            }

            $out[] = [
                'code' => $code,
                'name' => (string) ($channel['name'] ?? self::CHANNELS[$code]['label']),
                'group' => self::groupForChannel($code),
                'fee_flat' => (int) ($channel['total_fee']['flat'] ?? 0),
                'fee_percent' => (float) ($channel['total_fee']['percent'] ?? 0),
            ];
        }

        return $out;
    }

    /**
     * Fee kalkulator resmi Tripay untuk satu channel + nominal.
     * Dipakai untuk menampilkan total sebenarnya, bukan tabel rate hardcode.
     *
     * @return array{fee_merchant: int, fee_customer: int, total_fee: int, total_amount: int}
     */
    public function feeCalculator(string $channel, int $amount): array
    {
        $res = Http::acceptJson()->withToken($this->apiKey())->timeout(30)
            ->get($this->baseUrl().'/merchant/fee-calculator', [
                'code' => $channel,
                'amount' => $amount,
            ]);

        $json = $res->json() ?? [];

        if (! $res->successful() || ($json['success'] ?? false) !== true) {
            throw new \RuntimeException((string) ($json['message'] ?? 'Kalkulasi fee Tripay gagal.'));
        }

        $data = $json['data'] ?? [];

        return [
            'fee_merchant' => (int) ($data['fee_merchant'] ?? 0),
            'fee_customer' => (int) ($data['fee_customer'] ?? 0),
            'total_fee' => (int) ($data['total_fee'] ?? 0),
            'total_amount' => (int) ($data['amount'] ?? $amount),
        ];
    }
}
