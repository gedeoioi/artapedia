<?php

namespace App\Filament\Resources\Transactions\Schemas;

use Filament\Forms\Components\DateTimePicker;
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
                    ->prefix('$'),
                TextInput::make('sell_price')
                    ->required()
                    ->numeric()
                    ->default(0)
                    ->prefix('$'),
                TextInput::make('admin_fee')
                    ->required()
                    ->numeric()
                    ->default(0),
                TextInput::make('gateway_fee')
                    ->required()
                    ->numeric()
                    ->default(0),
                TextInput::make('total_amount')
                    ->required()
                    ->numeric()
                    ->default(0),
                TextInput::make('profit')
                    ->required()
                    ->numeric()
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
                TextInput::make('supplier_trx_id'),
                TextInput::make('supplier_status'),
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
