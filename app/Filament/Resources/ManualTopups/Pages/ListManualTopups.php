<?php

namespace App\Filament\Resources\ManualTopups\Pages;

use App\Filament\Resources\ManualTopups\ManualTopupResource;
use Filament\Resources\Pages\ListRecords;

class ListManualTopups extends ListRecords
{
    protected static string $resource = ManualTopupResource::class;

    protected static ?string $title = 'Review Topup Manual';

    protected function getHeaderActions(): array
    {
        return [];
    }
}
