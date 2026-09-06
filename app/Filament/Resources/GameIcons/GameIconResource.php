<?php

namespace App\Filament\Resources\GameIcons;

use App\Filament\Resources\GameIcons\Pages\CreateGameIcon;
use App\Filament\Resources\GameIcons\Pages\EditGameIcon;
use App\Filament\Resources\GameIcons\Pages\ListGameIcons;
use App\Filament\Resources\GameIcons\Schemas\GameIconForm;
use App\Filament\Resources\GameIcons\Tables\GameIconsTable;
use App\Models\GameIcon;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class GameIconResource extends Resource
{
    protected static ?string $model = GameIcon::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return GameIconForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return GameIconsTable::configure($table);
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
            'index' => ListGameIcons::route('/'),
            'create' => CreateGameIcon::route('/create'),
            'edit' => EditGameIcon::route('/{record}/edit'),
        ];
    }
}
