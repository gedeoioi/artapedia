<?php

namespace App\Filament\Resources\Transactions\Schemas;

use App\Models\Transaction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class TransactionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('invoice_code')
                    ->required(),
                TextInput::make('user_id')
                    ->numeric(),
                TextInput::make('product_id')
                    ->required()
                    ->numeric(),
                TextInput::make('supplier_config_id')
                    ->numeric(),
                TextInput::make('payment_gateway_code')
                    ->required()
                    ->default('balance'),
                TextInput::make('target_user_id')
                    ->required(),
                TextInput::make('target_zone'),
                TextInput::make('nickname'),
                TextInput::make('quantity')
                    ->required()
                    ->numeric()
                    ->default(1),
                TextInput::make('cost_price')
                    ->required()
                    ->numeric()
                    ->default(0)
                    ->prefix('Rp'),
                TextInput::make('sell_price')
                    ->required()
                    ->numeric()
                    ->default(0)
                    ->prefix('Rp'),
                TextInput::make('admin_fee')
                    ->required()
                    ->numeric()
                    ->prefix('Rp')
                    ->default(0),
                TextInput::make('gateway_fee')
                    ->required()
                    ->numeric()
                    ->prefix('Rp')
                    ->default(0),
                TextInput::make('total_amount')
                    ->required()
                    ->numeric()
                    ->prefix('Rp')
                    ->default(0),
                TextInput::make('profit')
                    ->required()
                    ->numeric()
                    ->prefix('Rp')
                    ->default(0),
                TextInput::make('payment_method')
                    ->required()
                    ->default('balance'),
                TextInput::make('payment_reference'),
                Textarea::make('payment_payload')
                    ->columnSpanFull(),
                TextInput::make('status')
                    ->required()
                    ->default('pending'),
                TextInput::make('supplier_trx_id')
                    ->label('Supplier trx id')
                    ->visible(fn (?Transaction $record): bool => ($record?->quantity ?? 1) <= 1),
                TextInput::make('supplier_status')
                    ->label('Supplier status'),
                Repeater::make('payment_payload.supplier_orders')
                    ->label('Supplier trx id per pembelian')
                    ->visible(fn (?Transaction $record): bool => ($record?->quantity ?? 1) > 1)
                    ->schema([
                        TextInput::make('index')
                            ->label('Pembelian ke'),
                        TextInput::make('trxid')
                            ->label('Supplier trx id'),
                        TextInput::make('status')
                            ->label('Status'),
                        TextInput::make('sn')
                            ->label('Serial number / catatan supplier'),
                        Textarea::make('message')
                            ->label('Pesan error supplier')
                            ->columnSpanFull(),
                    ])
                    ->itemLabel(fn (array $state): string => 'Pembelian #'.($state['index'] ?? '?'))
                    ->columns(2)
                    ->addable(false)
                    ->deletable(false)
                    ->reorderable(false)
                    ->disabled()
                    ->dehydrated(false)
                    ->columnSpanFull(),
                DateTimePicker::make('paid_at'),
                DateTimePicker::make('processed_at'),
                TextInput::make('buyer_phone')
                    ->tel(),
                TextInput::make('buyer_email')
                    ->email(),
                Textarea::make('notes')
                    ->columnSpanFull(),
                Textarea::make('meta')
                    ->columnSpanFull(),
            ]);
    }
}
