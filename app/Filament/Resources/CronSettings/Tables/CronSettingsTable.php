<?php

namespace App\Filament\Resources\CronSettings\Tables;

use App\Models\CronSetting;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
class CronSettingsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('label')
                    ->label('Tugas')
                    ->searchable(),
                TextColumn::make('interval_minutes')
                    ->label('Interval')
                    ->formatStateUsing(fn ($state) => CronSetting::INTERVAL_OPTIONS[$state] ?? "Tiap {$state} menit")
                    ->sortable(),
                ToggleColumn::make('is_active')
                    ->label('Aktif'),
                TextColumn::make('last_run_at')
                    ->label('Terakhir jalan')
                    ->dateTime()
                    ->sortable()
                    ->placeholder('Belum pernah'),
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('runNow')
                    ->label('Jalankan sekarang')
                    ->requiresConfirmation()
                    ->action(function ($record) {
                        $record->update(['last_run_at' => now()->subMinutes($record->interval_minutes + 1)]);
                        \Filament\Notifications\Notification::make()
                            ->title('Akan jalan pada menit berikutnya (schedule:work tiap menit)')
                            ->success()
                            ->send();
                    }),
            ])
            ->paginated(false);
    }
}
