<?php

namespace App\Filament\Resources\SupplierConfigs;

use App\Filament\Resources\SupplierConfigs\Pages\CreateSupplierConfig;
use App\Filament\Resources\SupplierConfigs\Pages\EditSupplierConfig;
use App\Filament\Resources\SupplierConfigs\Pages\ListSupplierConfigs;
use App\Filament\Resources\SupplierConfigs\Schemas\SupplierConfigForm;
use App\Filament\Resources\SupplierConfigs\Tables\SupplierConfigsTable;
use App\Models\SupplierConfig;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class SupplierConfigResource extends Resource
{
    protected static ?string $model = SupplierConfig::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return SupplierConfigForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SupplierConfigsTable::configure($table);
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
            'index' => ListSupplierConfigs::route('/'),
            'create' => CreateSupplierConfig::route('/create'),
            'edit' => EditSupplierConfig::route('/{record}/edit'),
        ];
    }
}
