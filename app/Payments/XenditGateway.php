<?php

namespace App\Payments;

use Illuminate\Support\Facades\Http;

class XenditGateway extends BasePaymentGateway
{
    public function code(): string
    {
        return 'xendit';
    }

    protected function baseUrl(): string
    {
        return $this->credentials['base_url'] ?? 'https://api.xendit.co';
    }

    protected function auth(): array
    {
        $secret = $this->credentials['secret_key'] ?? $this->credentials['api_key'] ?? '';

        return [$secret, ''];
    }

    public function createPayment(array $params): array
    {
        $res = Http::withBasicAuth($this->auth()[0], $this->auth()[1])
            ->post($this->baseUrl().'/v2/invoices', [
                'external_id' => $params['reference_id'],
                'amount' => (int) $params['amount'],
                'description' => $params['description'] ?? $params['reference_id'],
                'customer' => [
                    'email' => $params['customer_email'] ?? null,
                    'mobile_number' => $params['customer_phone'] ?? null,
                ],
                'success_redirect_url' => $params['success_url'] ?? null,
                'failure_redirect_url' => $params['failure_url'] ?? null,
            ]);

        $json = $res->json() ?? [];

        return [
            'ok' => $res->successful(),
            'reference_id' => $params['reference_id'],
            'gateway_ref' => $json['id'] ?? null,
            'pay_url' => $json['invoice_url'] ?? null,
            'raw' => $json,
        ];
    }

    public function handleCallback(array $payload, array $headers = []): array
    {
        $token = $this->credentials['callback_token'] ?? '';
        $provided = $this->headerValue($headers, 'x-callback-token');

        if ($token === '' || $provided === '' || ! hash_equals($token, $provided)) {
            return ['ok' => false, 'status' => 'invalid_signature', 'reference_id' => $payload['external_id'] ?? null];
        }

        $status = strtolower($payload['status'] ?? '');

        return [
            'ok' => true,
            'reference_id' => $payload['external_id'] ?? null,
            'amount' => isset($payload['amount']) ? (int) $payload['amount'] : null,
            'status' => $status === 'paid' ? 'paid' : ($status === 'expired' ? 'expired' : 'pending'),
            'raw' => $payload,
        ];
    }

    public function checkStatus(string $referenceId): array
    {
        $res = Http::withBasicAuth($this->auth()[0], $this->auth()[1])
            ->get($this->baseUrl().'/v2/invoices/'.$referenceId);

        $json = $res->json() ?? [];
        $status = strtolower($json['status'] ?? '');

        return [
            'ok' => $res->successful(),
            'reference_id' => $referenceId,
            'status' => $status === 'paid' ? 'paid' : ($status === 'expired' ? 'expired' : 'pending'),
            'raw' => $json,
        ];
    }
}
