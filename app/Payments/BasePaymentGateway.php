<?php

namespace App\Payments;

use App\Contracts\PaymentGatewayInterface;
use Illuminate\Support\Facades\Http;

abstract class BasePaymentGateway implements PaymentGatewayInterface
{
    public function __construct(protected array $credentials = [], protected bool $sandbox = true) {}

    protected function callbackBase(string $gateway): string
    {
        return rtrim(config('app.url'), '/').'/webhook/payment/'.$gateway;
    }
}
