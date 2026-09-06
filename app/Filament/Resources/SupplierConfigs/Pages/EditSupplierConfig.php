<?php

namespace App\Filament\Resources\SupplierConfigs\Pages;

use App\Filament\Resources\SupplierConfigs\SupplierConfigResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditSupplierConfig extends EditRecord
{
    protected static string $resource = SupplierConfigResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
