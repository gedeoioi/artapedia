<?php

namespace App\Filament\Resources\PaymentGatewayConfigs\Pages;

use App\Filament\Resources\PaymentGatewayConfigs\PaymentGatewayConfigResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePaymentGatewayConfig extends CreateRecord
{
    protected static string $resource = PaymentGatewayConfigResource::class;
}
