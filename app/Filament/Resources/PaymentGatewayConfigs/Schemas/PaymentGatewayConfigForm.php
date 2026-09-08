<?php

namespace App\Filament\Resources\PaymentGatewayConfigs\Schemas;

use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class PaymentGatewayConfigForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('code')
                    ->required(),
                TextInput::make('name')
                    ->required(),
                TextInput::make('gateway_class')
                    ->required()
                    ->datalist(array_values(config('artapedia.gateways', []))),
                Toggle::make('is_active')
                    ->required(),
                Toggle::make('is_sandbox')
                    ->required()
                    ->label('Sandbox mode'),
                TextInput::make('sort_order')
                    ->required()
                    ->numeric()
                    ->default(0)
                    ->label('Urutan tampil di checkout'),
                KeyValue::make('credentials')
                    ->columnSpanFull()
                    ->keyLabel('Key')
                    ->valueLabel('Value')
                    ->helperText('Tersimpan terenkripsi. Xendit: secret_key + callback_token. Duitku: merchant_code + api_key. iPaymu: va + secret (opsional callback_secret jika berbeda dari VA).'),
                TextInput::make('fee_flat')
                    ->label('Biaya flat')
                    ->required()
                    ->numeric()
                    ->prefix('Rp')
                    ->default(0),
                TextInput::make('fee_percent')
                    ->label('Biaya persen')
                    ->required()
                    ->numeric()
                    ->suffix('%')
                    ->default(0),
            ]);
    }
}
