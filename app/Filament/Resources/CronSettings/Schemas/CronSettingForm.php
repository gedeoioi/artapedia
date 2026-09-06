<?php

namespace App\Filament\Resources\CronSettings\Schemas;

use App\Models\CronSetting;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class CronSettingForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('label')
                    ->label('Nama tugas')
                    ->disabled(),
                Select::make('interval_minutes')
                    ->label('Interval jalan')
                    ->options(CronSetting::INTERVAL_OPTIONS)
                    ->required()
                    ->helperText('Berlaku mulai menit berikutnya setelah disimpan.'),
                Toggle::make('is_active')
                    ->label('Aktif')
                    ->required(),
            ]);
    }
}
