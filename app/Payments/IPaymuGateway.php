<?php

namespace App\Payments;

use Illuminate\Support\Facades\Http;

class IPaymuGateway extends BasePaymentGateway
{
    public const MINIMUM_AMOUNT = 10_000;

    public function code(): string
    {
        return 'ipaymu';
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

        $body = [
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
            ->post($this->baseUrl().'/payment');

        $json = $res->json() ?? [];
        // Dokumentasi terbaru memakai `SessionID`, sedangkan sebagian respons
        // lama/sandbox memakai `SessionId`. Terima keduanya.
        $sessionId = $json['Data']['SessionID'] ?? $json['Data']['SessionId'] ?? null;
        $checkoutUrl = $json['Data']['Url'] ?? null;
        $ok = $res->successful()
            && (int) ($json['Status'] ?? 0) === 200
            && filled($sessionId)
            && filled($checkoutUrl);

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
