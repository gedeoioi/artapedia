<?php

namespace App\Filament\Resources\CronSettings\Pages;

use App\Filament\Resources\CronSettings\CronSettingResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCronSettings extends ListRecords
{
    protected static string $resource = CronSettingResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
