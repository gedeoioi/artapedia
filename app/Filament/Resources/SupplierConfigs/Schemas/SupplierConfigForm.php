<?php

namespace App\Filament\Resources\SupplierConfigs\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class SupplierConfigForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('code')
                    ->required(),
                TextInput::make('name')
                    ->required(),
                TextInput::make('provider_class')
                    ->required()
                    ->datalist(array_values(config('artapedia.suppliers', []))),
                Toggle::make('is_active')
                    ->required(),
                Toggle::make('is_sandbox')
                    ->required()
                    ->label('Sandbox mode'),
                TextInput::make('priority')
                    ->required()
                    ->numeric()
                    ->default(0),
                KeyValue::make('credentials')
                    ->columnSpanFull()
                    ->keyLabel('Key')
                    ->valueLabel('Value')
                    ->helperText('Tersimpan terenkripsi. VIP Reseller: api_id + api_key (sign = md5(api_id + api_key)). Digiflazz: username + api_key + webhook_secret (opsional, untuk verifikasi webhook) + base_url (opsional).'),
                TextInput::make('cached_balance')
                    ->required()
                    ->numeric()
                    ->default(0),
                DateTimePicker::make('last_sync_at'),
            ]);
    }
}
