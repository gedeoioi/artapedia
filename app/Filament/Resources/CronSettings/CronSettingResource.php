<?php

namespace App\Filament\Resources\CronSettings;

use App\Filament\Resources\CronSettings\Pages\CreateCronSetting;
use App\Filament\Resources\CronSettings\Pages\EditCronSetting;
use App\Filament\Resources\CronSettings\Pages\ListCronSettings;
use App\Filament\Resources\CronSettings\Schemas\CronSettingForm;
use App\Filament\Resources\CronSettings\Tables\CronSettingsTable;
use App\Models\CronSetting;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class CronSettingResource extends Resource
{
    protected static ?string $model = CronSetting::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClock;

    protected static ?string $navigationLabel = 'Jadwal Cron / Polling';

    protected static string|\UnitEnum|null $navigationGroup = 'Pengaturan';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return CronSettingForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CronSettingsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCronSettings::route('/'),
            'create' => CreateCronSetting::route('/create'),
            'edit' => EditCronSetting::route('/{record}/edit'),
        ];
    }
}
