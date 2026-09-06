<?php

namespace App\Filament\Resources\GameIcons\Schemas;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class GameIconForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('game_name')
                    ->required(),
                TextInput::make('slug')
                    ->required(),
                FileUpload::make('icon_path')
                    ->image()
                    ->directory('game-icons')
                    ->helperText('Upload 1 icon default per game (bukan per varian).'),
                Toggle::make('is_active')
                    ->required(),
            ]);
    }
}
