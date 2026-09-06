<?php

namespace App\Payments;

use Illuminate\Support\Facades\Http;

class DuitkuGateway extends BasePaymentGateway
{
    public function code(): string
    {
        return 'duitku';
    }

    protected function baseUrl(): string
    {
        return $this->credentials['base_url']
            ?? ($this->sandbox ? 'https://api-sandbox.duitku.com/api/merchant' : 'https://api-prod.duitku.com/api/merchant');
    }

    public function createPayment(array $params): array
    {
        $merchantCode = $this->credentials['merchant_code'] ?? '';
        $apiKey = $this->credentials['api_key'] ?? '';
        $signature = md5($merchantCode.$params['reference_id'].$params['amount'].$apiKey);

        $res = Http::post($this->baseUrl().'/createInvoice', [
            'merchantCode' => $merchantCode,
            'paymentAmount' => (int) $params['amount'],
            'merchantOrderId' => $params['reference_id'],
            'productDetails' => $params['description'] ?? $params['reference_id'],
            'customerVaName' => $params['customer_name'] ?? 'ArtaPedia Buyer',
            'email' => $params['customer_email'] ?? '',
            'phoneNumber' => $params['customer_phone'] ?? '',
            'callbackUrl' => $this->callbackBase('duitku'),
            'returnUrl' => $params['success_url'] ?? config('app.url'),
            'signature' => $signature,
            'expiryPeriod' => 60,
        ]);

        $json = $res->json() ?? [];

        return [
            'ok' => $res->successful() && ($json['statusCode'] ?? '') === '00',
            'reference_id' => $params['reference_id'],
            'gateway_ref' => $json['reference'] ?? null,
            'pay_url' => $json['paymentUrl'] ?? null,
            'raw' => $json,
        ];
    }

    public function handleCallback(array $payload, array $headers = []): array
    {
        $merchantCode = $this->credentials['merchant_code'] ?? '';
        $apiKey = $this->credentials['api_key'] ?? '';
        $expected = md5($merchantCode.($payload['merchantOrderId'] ?? '').($payload['amount'] ?? '').$apiKey);

        if (! hash_equals($expected, (string) ($payload['signature'] ?? ''))) {
            return ['ok' => false, 'status' => 'invalid_signature', 'reference_id' => $payload['merchantOrderId'] ?? null];
        }

        $code = $payload['resultCode'] ?? '';

        return [
            'ok' => true,
            'reference_id' => $payload['merchantOrderId'] ?? null,
            'status' => $code === '00' ? 'paid' : 'pending',
            'raw' => $payload,
        ];
    }

    public function checkStatus(string $referenceId): array
    {
        $merchantCode = $this->credentials['merchant_code'] ?? '';
        $apiKey = $this->credentials['api_key'] ?? '';
        $signature = md5($merchantCode.$referenceId.$apiKey);

        $res = Http::post($this->baseUrl().'/transactionStatus', [
            'merchantCode' => $merchantCode,
            'merchantOrderId' => $referenceId,
            'signature' => $signature,
        ]);

        $json = $res->json() ?? [];

        return [
            'ok' => $res->successful(),
            'reference_id' => $referenceId,
            'status' => ($json['resultCode'] ?? '') === '00' ? 'paid' : 'pending',
            'raw' => $json,
        ];
    }
}
