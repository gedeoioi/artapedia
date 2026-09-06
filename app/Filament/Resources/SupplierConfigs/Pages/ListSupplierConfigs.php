<?php

namespace App\Filament\Resources\SupplierConfigs\Pages;

use App\Filament\Resources\SupplierConfigs\SupplierConfigResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSupplierConfigs extends ListRecords
{
    protected static string $resource = SupplierConfigResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
