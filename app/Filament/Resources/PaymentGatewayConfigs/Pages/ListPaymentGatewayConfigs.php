<?php

namespace App\Filament\Resources\PaymentGatewayConfigs\Pages;

use App\Filament\Resources\PaymentGatewayConfigs\PaymentGatewayConfigResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPaymentGatewayConfigs extends ListRecords
{
    protected static string $resource = PaymentGatewayConfigResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
