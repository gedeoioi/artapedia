<?php

namespace App\Filament\Resources\GameIcons\Pages;

use App\Filament\Resources\GameIcons\GameIconResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditGameIcon extends EditRecord
{
    protected static string $resource = GameIconResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
