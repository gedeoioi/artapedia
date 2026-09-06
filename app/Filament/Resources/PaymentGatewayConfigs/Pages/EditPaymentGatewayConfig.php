<?php

namespace App\Filament\Resources\PaymentGatewayConfigs\Pages;

use App\Filament\Resources\PaymentGatewayConfigs\PaymentGatewayConfigResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditPaymentGatewayConfig extends EditRecord
{
    protected static string $resource = PaymentGatewayConfigResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
