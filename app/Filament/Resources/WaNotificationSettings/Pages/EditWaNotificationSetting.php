<?php

namespace App\Filament\Resources\WaNotificationSettings\Pages;

use App\Filament\Resources\WaNotificationSettings\WaNotificationSettingResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditWaNotificationSetting extends EditRecord
{
    protected static string $resource = WaNotificationSettingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
