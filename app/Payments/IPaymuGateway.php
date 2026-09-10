<?php

namespace App\Payments;

use Illuminate\Support\Facades\Http;

class IPaymuGateway extends BasePaymentGateway
{
    public const MINIMUM_AMOUNT = 10_000;

    public const CHECKOUT_CHANNELS = [
        'qris' => [
            'label' => 'QRIS',
            'channels' => ['mpm' => 'QRIS'],
        ],
        'ewallet' => [
            'label' => 'E-Wallet',
            'channels' => ['dana' => 'DANA', 'shopeepay' => 'ShopeePay'],
        ],
        'va' => [
            'label' => 'Virtual Account',
            'channels' => [
                'bca' => 'BCA',
                'bni' => 'BNI',
                'mandiri' => 'Mandiri',
                'bri' => 'BRI',
                'bsi' => 'BSI',
                'cimb' => 'CIMB Niaga',
                'permata' => 'Permata',
                'danamon' => 'Danamon',
                'btn' => 'BTN',
                'bpd_bali' => 'BPD Bali',
                'bmi' => 'Bank Muamalat',
                'bag' => 'Bank Artha Graha',
            ],
        ],
        'cstore' => [
            'label' => 'Gerai Retail',
            'channels' => ['alfamart' => 'Alfamart', 'indomaret' => 'Indomaret'],
        ],
    ];

    public static function supportsCheckoutChannel(?string $method, ?string $channel): bool
    {
        return isset(self::CHECKOUT_CHANNELS[$method]['channels'][$channel]);
    }

    public static function minimumAmountFor(?string $method, ?string $channel): int
    {
        return $method === 'cstore' && $channel === 'indomaret' ? 15_000 : self::MINIMUM_AMOUNT;
    }

    public function code(): string
    {
        return 'ipaymu';
    }

    public function activePaymentChannels(): array
    {
        $va = trim((string) ($this->credentials['va'] ?? ''));
        $secret = trim((string) ($this->credentials['secret'] ?? ''));
        if ($va === '' || $secret === '') {
            throw new \RuntimeException('VA atau API Key iPaymu belum dikonfigurasi.');
        }

        // Endpoint GET tanpa query memakai JSON object kosong sebagai bagian
        // signature sesuai dokumentasi iPaymu API v2.
        $res = Http::acceptJson()->withHeaders([
            'va' => $va,
            'signature' => $this->sign('GET', '{}'),
            'timestamp' => now()->format('YmdHis'),
        ])->timeout(30)->get($this->baseUrl().'/payment-channels');
        $json = $res->json() ?? [];

        if (! $res->successful() || (int) ($json['Status'] ?? 0) !== 200) {
            throw new \RuntimeException((string) ($json['Message'] ?? 'Daftar channel iPaymu gagal dimuat.'));
        }

        $active = [];
        foreach ($json['Data'] ?? [] as $method) {
            $methodCode = strtolower((string) ($method['Code'] ?? ''));
            if (! isset(self::CHECKOUT_CHANNELS[$methodCode])) {
                continue;
            }

            foreach ($method['Channels'] ?? [] as $channel) {
                $channelCode = strtolower((string) ($channel['Code'] ?? ''));
                $featureStatus = strtolower((string) ($channel['FeatureStatus'] ?? 'active'));
                if ($featureStatus === 'active' && self::supportsCheckoutChannel($methodCode, $channelCode)) {
                    $active[$methodCode][] = $channelCode;
                }
            }
        }

        return $active;
    }

    protected function baseUrl(): string
    {
        return $this->credentials['base_url']
            ?? ($this->sandbox ? 'https://sandbox.ipaymu.com/api/v2' : 'https://my.ipaymu.com/api/v2');
    }

    protected function sign(string $method, string $bodyHash): string
    {
        $va = trim((string) ($this->credentials['va'] ?? ''));
        $secret = trim((string) ($this->credentials['secret'] ?? ''));

        $stringToSign = strtoupper($method).':'.$va.':'.strtolower($bodyHash).':'.$secret;

        return hash_hmac('sha256', $stringToSign, $secret);
    }

    public function createPayment(array $params): array
    {
        $va = trim((string) ($this->credentials['va'] ?? ''));
        $secret = trim((string) ($this->credentials['secret'] ?? ''));
        $description = (string) ($params['description'] ?? $params['reference_id']);

        if ($va === '' || $secret === '') {
            return [
                'ok' => false,
                'reference_id' => $params['reference_id'],
                'gateway_ref' => null,
                'pay_url' => null,
                'message' => 'VA atau API Key iPaymu belum dikonfigurasi.',
                'raw' => [],
            ];
        }

        $direct = self::supportsCheckoutChannel(
            $params['payment_method'] ?? null,
            $params['payment_channel'] ?? null,
        );

        $body = $direct ? [
            'name' => (string) ($params['customer_name'] ?? 'ArtaPedia Buyer'),
            'phone' => (string) ($params['customer_phone'] ?? ''),
            'email' => (string) ($params['customer_email'] ?? ''),
            'amount' => (int) $params['amount'],
            'notifyUrl' => $this->callbackBase('ipaymu'),
            'referenceId' => $params['reference_id'],
            'paymentMethod' => $params['payment_method'],
            'paymentChannel' => $params['payment_channel'],
            'product' => [$description],
            'qty' => [1],
            'price' => [(int) $params['amount']],
            'comments' => $description,
            'feeDirection' => 'MERCHANT',
            'expired' => 1,
            'expiredType' => 'hours',
        ] : [
            'product' => [$description],
            'qty' => [1],
            'price' => [(int) $params['amount']],
            'description' => [$description],
            'notifyUrl' => $this->callbackBase('ipaymu'),
            'returnUrl' => $params['success_url'] ?? config('app.url'),
            'cancelUrl' => $params['failure_url'] ?? config('app.url'),
            'name' => (string) ($params['customer_name'] ?? 'ArtaPedia Buyer'),
            'phone' => (string) ($params['customer_phone'] ?? ''),
            'email' => (string) ($params['customer_email'] ?? ''),
            'buyerName' => (string) ($params['customer_name'] ?? 'ArtaPedia Buyer'),
            'buyerPhone' => (string) ($params['customer_phone'] ?? ''),
            'buyerEmail' => (string) ($params['customer_email'] ?? ''),
            'referenceId' => $params['reference_id'],
            'expired' => 1,
            'expiredType' => 'hours',
        ];

        // `account` pada API redirect adalah VA child account, bukan VA merchant
        // yang sudah dikirim melalui header. Hanya kirim bila memang dikonfigurasi.
        $childAccount = trim((string) ($this->credentials['account'] ?? ''));
        if ($childAccount !== '') {
            $body['account'] = $childAccount;
        }

        $bodyJson = json_encode($body, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $res = Http::acceptJson()->withHeaders([
            'va' => $va,
            'signature' => $this->sign('POST', hash('sha256', $bodyJson)),
            'timestamp' => now()->format('YmdHis'),
        ])->withBody($bodyJson, 'application/json')
            ->timeout(30)
            ->post($this->baseUrl().($direct ? '/payment/direct' : '/payment'));

        $json = $res->json() ?? [];
        // Dokumentasi terbaru memakai `SessionID`, sedangkan sebagian respons
        // lama/sandbox memakai `SessionId`. Terima keduanya.
        $sessionId = $direct
            ? ($json['Data']['TransactionId'] ?? $json['Data']['ReferenceId'] ?? null)
            : ($json['Data']['SessionID'] ?? $json['Data']['SessionId'] ?? null);
        $checkoutUrl = $json['Data']['Url'] ?? null;
        $ok = $res->successful()
            && (int) ($json['Status'] ?? 0) === 200
            && filled($sessionId)
            && ($direct || filled($checkoutUrl));

        $message = $json['Message'] ?? $json['message'] ?? 'iPaymu menolak pembuatan pembayaran.';
        if (is_array($message)) {
            $message = implode(' ', array_map('strval', $message));
        }

        return [
            'ok' => $ok,
            'reference_id' => $params['reference_id'],
            'gateway_ref' => $sessionId,
            'pay_url' => $checkoutUrl,
            'message' => (string) $message,
            'raw' => $json,
        ];
    }

    public function handleCallback(array $payload, array $headers = []): array
    {
        // Callback iPaymu memakai Merchant VA sebagai HMAC secret. Payload harus
        // dinormalisasi dan diurutkan sebelum dihitung (berbeda dari signature API).
        $secret = $this->credentials['callback_secret'] ?? $this->credentials['va'] ?? '';
        $provided = $this->headerValue($headers, 'x-signature');
        $normalized = $this->normalizeCallbackPayload($payload);
        $expected = hash_hmac('sha256', (string) json_encode($normalized), $secret);

        if ($secret === '' || $provided === '' || ! hash_equals($expected, $provided)) {
            return ['ok' => false, 'status' => 'invalid_signature', 'reference_id' => $payload['referenceId'] ?? null];
        }

        $status = strtolower($payload['status'] ?? $payload['Status'] ?? '');

        return [
            'ok' => true,
            'reference_id' => $payload['referenceId'] ?? $payload['reference_id'] ?? null,
            'amount' => isset($payload['amount']) ? (int) $payload['amount'] : null,
            'status' => in_array($status, ['berhasil', 'paid', 'success'], true) ? 'paid' : 'pending',
            'raw' => $payload,
        ];
    }

    protected function normalizeCallbackPayload(array $payload): array
    {
        unset($payload['signature']);

        foreach ($payload as $key => $value) {
            if (in_array($key, ['trx_id', 'status_code', 'transaction_status_code', 'paid_off'], true)) {
                $payload[$key] = (int) $value;
            } elseif ($key === 'is_escrow') {
                $payload[$key] = filter_var($value, FILTER_VALIDATE_BOOL);
            } elseif ($key === 'additional_info' && $value === '[]') {
                $payload[$key] = [];
            } elseif (! is_array($value)) {
                $payload[$key] = (string) $value;
            }
        }

        $payload['additional_info'] ??= [];
        ksort($payload, SORT_STRING);

        return $payload;
    }

    public function checkStatus(string $referenceId): array
    {
        $va = trim((string) ($this->credentials['va'] ?? ''));
        $body = ['referenceId' => $referenceId];
        $bodyJson = json_encode($body, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

        $res = Http::acceptJson()->withHeaders([
            'va' => $va,
            'signature' => $this->sign('POST', hash('sha256', $bodyJson)),
            'timestamp' => now()->format('YmdHis'),
        ])->withBody($bodyJson, 'application/json')
            ->timeout(30)
            ->post($this->baseUrl().'/payment/check');

        $json = $res->json() ?? [];
        $status = strtolower($json['Data']['Status'] ?? $json['Status'] ?? '');

        return [
            'ok' => $res->successful(),
            'reference_id' => $referenceId,
            'status' => in_array($status, ['berhasil', 'paid', 'success'], true) ? 'paid' : 'pending',
            'raw' => $json,
        ];
    }
}
