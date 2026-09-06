<?php

namespace App\Filament\Resources\PaymentGatewayConfigs;

use App\Filament\Resources\PaymentGatewayConfigs\Pages\CreatePaymentGatewayConfig;
use App\Filament\Resources\PaymentGatewayConfigs\Pages\EditPaymentGatewayConfig;
use App\Filament\Resources\PaymentGatewayConfigs\Pages\ListPaymentGatewayConfigs;
use App\Filament\Resources\PaymentGatewayConfigs\Schemas\PaymentGatewayConfigForm;
use App\Filament\Resources\PaymentGatewayConfigs\Tables\PaymentGatewayConfigsTable;
use App\Models\PaymentGatewayConfig;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class PaymentGatewayConfigResource extends Resource
{
    protected static ?string $model = PaymentGatewayConfig::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return PaymentGatewayConfigForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PaymentGatewayConfigsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPaymentGatewayConfigs::route('/'),
            'create' => CreatePaymentGatewayConfig::route('/create'),
            'edit' => EditPaymentGatewayConfig::route('/{record}/edit'),
        ];
    }
}
