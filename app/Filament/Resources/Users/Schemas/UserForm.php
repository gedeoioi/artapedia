<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Support\AdminRoles;
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
                    ->label('Saldo')
                    ->required()
                    ->numeric()
                    ->prefix('Rp')
                    ->default(0)
                    ->disabled()
                    ->dehydrated(false)
                    ->helperText('Ubah saldo hanya lewat aksi "Sesuaikan saldo" di daftar user (tercatat di ledger + audit trail).'),
                Select::make('roles')
                    ->label('Peran panel admin')
                    ->relationship('roles', 'name')
                    ->getOptionLabelFromRecordUsing(
                        fn ($record): string => AdminRoles::LABELS[$record->name] ?? $record->name
                    )
                    ->multiple()
                    ->preload()
                    ->helperText('Super Admin = akses penuh. Admin = tanpa kelola user. Operator = transaksi & review saja.'),
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
