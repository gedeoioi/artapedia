<?php

namespace App\Filament\Resources\GameIcons\Pages;

use App\Filament\Resources\GameIcons\GameIconResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListGameIcons extends ListRecords
{
    protected static string $resource = GameIconResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
