<?php

namespace App\Filament\Resources\WaNotificationSettings;

use App\Filament\Resources\WaNotificationSettings\Pages\CreateWaNotificationSetting;
use App\Filament\Resources\WaNotificationSettings\Pages\EditWaNotificationSetting;
use App\Filament\Resources\WaNotificationSettings\Pages\ListWaNotificationSettings;
use App\Filament\Resources\WaNotificationSettings\Schemas\WaNotificationSettingForm;
use App\Filament\Resources\WaNotificationSettings\Tables\WaNotificationSettingsTable;
use App\Models\WaNotificationSetting;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class WaNotificationSettingResource extends Resource
{
    protected static ?string $model = WaNotificationSetting::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return WaNotificationSettingForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return WaNotificationSettingsTable::configure($table);
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
            'index' => ListWaNotificationSettings::route('/'),
            'create' => CreateWaNotificationSetting::route('/create'),
            'edit' => EditWaNotificationSetting::route('/{record}/edit'),
        ];
    }
}
