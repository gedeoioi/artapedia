<?php

namespace App\Filament\Resources\WaNotificationSettings\Pages;

use App\Filament\Resources\WaNotificationSettings\WaNotificationSettingResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListWaNotificationSettings extends ListRecords
{
    protected static string $resource = WaNotificationSettingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
