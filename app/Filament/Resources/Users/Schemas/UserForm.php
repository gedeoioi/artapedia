<?php

namespace App\Filament\Resources\Users\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required(),
                TextInput::make('email')
                    ->label('Email address')
                    ->email()
                    ->required(),
                DateTimePicker::make('email_verified_at'),
                TextInput::make('password')
                    ->password()
                    ->required(),
                TextInput::make('balance')
                    ->required()
                    ->numeric()
                    ->default(0)
                    ->disabled()
                    ->helperText('Ubah saldo hanya via mutasi (ledger), bukan edit langsung.'),
                Select::make('level')
                    ->required()
                    ->options(['admin' => 'Admin', 'vip' => 'Reseller VIP', 'biasa' => 'Reseller Biasa', 'member' => 'Member'])
                    ->default('member'),
                Select::make('status')
                    ->required()
                    ->options(['active' => 'Aktif', 'suspended' => 'Suspend'])
                    ->default('active'),
                TextInput::make('phone')
                    ->tel(),
                TextInput::make('whatsapp'),
            ]);
    }
}
