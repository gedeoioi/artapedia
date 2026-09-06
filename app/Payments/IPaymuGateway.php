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
        $secret = $this->credentials['secret'] ?? '';
        $bodyHash = hash('sha256', json_encode($payload));
        $expected = $this->sign('POST', $bodyHash);
        $provided = $headers['signature'] ?? $headers['Signature'] ?? '';

        if ($secret !== '' && $provided !== '' && ! hash_equals($expected, (string) $provided)) {
            return ['ok' => false, 'status' => 'invalid_signature', 'reference_id' => $payload['referenceId'] ?? null];
        }

        $status = strtolower($payload['status'] ?? $payload['Status'] ?? '');

        return [
            'ok' => true,
            'reference_id' => $payload['referenceId'] ?? $payload['reference_id'] ?? null,
            'status' => in_array($status, ['berhasil', 'paid', 'success'], true) ? 'paid' : 'pending',
            'raw' => $payload,
        ];
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
