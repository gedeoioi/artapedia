<?php

namespace App\Payments;

use Illuminate\Support\Facades\Http;

class IPaymuGateway extends BasePaymentGateway
{
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
        $va = $this->credentials['va'] ?? '';
        $secret = $this->credentials['secret'] ?? '';

        $stringToSign = strtoupper($method).':'.$va.':'.strtolower($bodyHash).':'.$secret;

        return hash_hmac('sha256', $stringToSign, $secret);
    }

    public function createPayment(array $params): array
    {
        $va = $this->credentials['va'] ?? '';
        $body = [
            'name' => $params['customer_name'] ?? 'ArtaPedia Buyer',
            'phone' => $params['customer_phone'] ?? '',
            'email' => $params['customer_email'] ?? '',
            'amount' => (int) $params['amount'],
            'referenceId' => $params['reference_id'],
            'description' => $params['description'] ?? $params['reference_id'],
            'expired' => 60,
            'returnUrl' => $params['success_url'] ?? config('app.url'),
            'cancelUrl' => $params['failure_url'] ?? config('app.url'),
            'notifyUrl' => $this->callbackBase('ipaymu'),
        ];

        $bodyJson = json_encode($body);
        $res = Http::withHeaders([
            'Content-Type' => 'application/json',
            'va' => $va,
            'signature' => $this->sign('POST', hash('sha256', $bodyJson)),
            'timestamp' => now()->format('YmdHis'),
        ])->post($this->baseUrl().'/payment', $body);

        $json = $res->json() ?? [];

        return [
            'ok' => $res->successful() && (($json['Status'] ?? 0) == 200),
            'reference_id' => $params['reference_id'],
            'gateway_ref' => $json['Data']['SessionId'] ?? null,
            'pay_url' => $json['Data']['Url'] ?? null,
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
        $va = $this->credentials['va'] ?? '';
        $body = ['referenceId' => $referenceId];
        $bodyJson = json_encode($body);

        $res = Http::withHeaders([
            'Content-Type' => 'application/json',
            'va' => $va,
            'signature' => $this->sign('POST', hash('sha256', $bodyJson)),
            'timestamp' => now()->format('YmdHis'),
        ])->post($this->baseUrl().'/payment/check', $body);

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
