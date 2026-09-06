<?php

namespace App\Filament\Resources\WaNotificationSettings\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class WaNotificationSettingForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required(),
                Toggle::make('is_active')
                    ->required(),
                TextInput::make('recipient'),
                Textarea::make('template')
                    ->columnSpanFull(),
                TextInput::make('schedule')
                    ->required()
                    ->default('on_event'),
                TextInput::make('api_url')
                    ->url(),
                DateTimePicker::make('last_sent_at'),
            ]);
    }
}
