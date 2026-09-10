<?php

namespace App\Filament\Resources\PaymentGatewayConfigs\Schemas;

use App\Payments\IPaymuGateway;
use Filament\Forms\Components\CheckboxList;
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
                    ->required()
                    ->live(),
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
                    ->helperText('Tersimpan terenkripsi. Xendit: secret_key + callback_token. Duitku: merchant_code + api_key. iPaymu: va + secret (isi secret dengan API Key; kredensial Sandbox dan Production berbeda; callback_secret opsional jika berbeda dari VA).'),
                TextInput::make('fee_flat')
                    ->label('Biaya flat default')
                    ->required()
                    ->numeric()
                    ->prefix('Rp')
                    ->default(0)
                    ->helperText('Dipakai untuk topup saldo dan gateway yang tidak memiliki pengaturan channel.'),
                TextInput::make('fee_percent')
                    ->label('Biaya persen default')
                    ->required()
                    ->numeric()
                    ->suffix('%')
                    ->default(0),
                CheckboxList::make('channel_settings.qris.channels')
                    ->label('Channel QRIS aktif')
                    ->options(IPaymuGateway::CHECKOUT_CHANNELS['qris']['channels'])
                    ->default(array_keys(IPaymuGateway::CHECKOUT_CHANNELS['qris']['channels']))
                    ->columns(3)
                    ->bulkToggleable()
                    ->visible(fn ($get): bool => strtolower((string) $get('code')) === 'ipaymu')
                    ->columnSpanFull(),
                TextInput::make('channel_settings.qris.fee_flat')
                    ->label('Biaya flat QRIS')
                    ->numeric()->minValue(0)->prefix('Rp')->default(0)
                    ->visible(fn ($get): bool => strtolower((string) $get('code')) === 'ipaymu'),
                TextInput::make('channel_settings.qris.fee_percent')
                    ->label('Biaya persen QRIS')
                    ->numeric()->minValue(0)->maxValue(100)->suffix('%')->default(0)
                    ->visible(fn ($get): bool => strtolower((string) $get('code')) === 'ipaymu'),
                CheckboxList::make('channel_settings.ewallet.channels')
                    ->label('Channel E-Wallet aktif')
                    ->options(IPaymuGateway::CHECKOUT_CHANNELS['ewallet']['channels'])
                    ->default(array_keys(IPaymuGateway::CHECKOUT_CHANNELS['ewallet']['channels']))
                    ->columns(3)
                    ->bulkToggleable()
                    ->visible(fn ($get): bool => strtolower((string) $get('code')) === 'ipaymu')
                    ->columnSpanFull(),
                TextInput::make('channel_settings.ewallet.fee_flat')
                    ->label('Biaya flat E-Wallet')
                    ->numeric()->minValue(0)->prefix('Rp')->default(0)
                    ->visible(fn ($get): bool => strtolower((string) $get('code')) === 'ipaymu'),
                TextInput::make('channel_settings.ewallet.fee_percent')
                    ->label('Biaya persen E-Wallet')
                    ->numeric()->minValue(0)->maxValue(100)->suffix('%')->default(0)
                    ->visible(fn ($get): bool => strtolower((string) $get('code')) === 'ipaymu'),
                CheckboxList::make('channel_settings.va.channels')
                    ->label('Bank Virtual Account aktif')
                    ->options(IPaymuGateway::CHECKOUT_CHANNELS['va']['channels'])
                    ->default(array_keys(IPaymuGateway::CHECKOUT_CHANNELS['va']['channels']))
                    ->columns(3)
                    ->bulkToggleable()
                    ->visible(fn ($get): bool => strtolower((string) $get('code')) === 'ipaymu')
                    ->columnSpanFull(),
                TextInput::make('channel_settings.va.fee_flat')
                    ->label('Biaya flat Virtual Account')
                    ->numeric()->minValue(0)->prefix('Rp')->default(0)
                    ->visible(fn ($get): bool => strtolower((string) $get('code')) === 'ipaymu'),
                TextInput::make('channel_settings.va.fee_percent')
                    ->label('Biaya persen Virtual Account')
                    ->numeric()->minValue(0)->maxValue(100)->suffix('%')->default(0)
                    ->visible(fn ($get): bool => strtolower((string) $get('code')) === 'ipaymu'),
                CheckboxList::make('channel_settings.cstore.channels')
                    ->label('Gerai Retail aktif')
                    ->options(IPaymuGateway::CHECKOUT_CHANNELS['cstore']['channels'])
                    ->default(array_keys(IPaymuGateway::CHECKOUT_CHANNELS['cstore']['channels']))
                    ->columns(3)
                    ->bulkToggleable()
                    ->visible(fn ($get): bool => strtolower((string) $get('code')) === 'ipaymu')
                    ->columnSpanFull(),
                TextInput::make('channel_settings.cstore.fee_flat')
                    ->label('Biaya flat Gerai Retail')
                    ->numeric()->minValue(0)->prefix('Rp')->default(0)
                    ->visible(fn ($get): bool => strtolower((string) $get('code')) === 'ipaymu'),
                TextInput::make('channel_settings.cstore.fee_percent')
                    ->label('Biaya persen Gerai Retail')
                    ->numeric()->minValue(0)->maxValue(100)->suffix('%')->default(0)
                    ->visible(fn ($get): bool => strtolower((string) $get('code')) === 'ipaymu'),
            ]);
    }
}
