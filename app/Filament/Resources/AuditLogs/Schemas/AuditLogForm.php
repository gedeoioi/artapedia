<?php

namespace App\Filament\Resources\AuditLogs\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class AuditLogForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('user_id')
                    ->numeric(),
                TextInput::make('action')
                    ->required(),
                TextInput::make('auditable_type'),
                TextInput::make('auditable_id')
                    ->numeric(),
                Textarea::make('old_values')
                    ->columnSpanFull(),
                Textarea::make('new_values')
                    ->columnSpanFull(),
                TextInput::make('ip'),
                Textarea::make('user_agent')
                    ->columnSpanFull(),
            ]);
    }
}
