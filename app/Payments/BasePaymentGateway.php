<?php

namespace App\Payments;

use App\Contracts\PaymentGatewayInterface;

abstract class BasePaymentGateway implements PaymentGatewayInterface
{
    public function __construct(protected array $credentials = [], protected bool $sandbox = true) {}

    protected function callbackBase(string $gateway): string
    {
        return rtrim(config('app.url'), '/').'/webhook/payment/'.$gateway;
    }

    protected function headerValue(array $headers, string $name): string
    {
        foreach ($headers as $key => $value) {
            if (strcasecmp((string) $key, $name) === 0) {
                return is_array($value) ? (string) ($value[0] ?? '') : (string) $value;
            }
        }

        return '';
    }
}
