<?php

namespace App\Filament\Resources\SupplierConfigs\Tables;

use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Infolists\Components\CodeEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;

class SupplierConfigsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')
                    ->searchable(),
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('provider_class')
                    ->searchable(),
                ToggleColumn::make('is_active')
                    ->label('Aktif'),
                ToggleColumn::make('is_sandbox')
                    ->label('Sandbox'),
                TextColumn::make('priority')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('cached_balance')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('last_sync_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('last_test_ok')
                    ->label('Tes Terakhir')
                    ->badge()
                    ->formatStateUsing(fn ($state, $record) => $record->last_test_at === null
                        ? 'Belum dites'
                        : ($state ? 'Berhasil' : 'Gagal'))
                    ->color(fn ($state, $record) => $record->last_test_at === null
                        ? 'gray'
                        : ($state ? 'success' : 'danger'))
                    ->description(fn ($record) => $record->last_test_at
                        ? $record->last_test_at->format('d M Y H:i').' - '.($record->last_test_summary ?? '')
                        : null),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('testConnection')
                    ->label('Tes Koneksi')
                    ->icon(Heroicon::OutlinedSignal)
                    ->color('info')
                    ->requiresConfirmation()
                    ->modalHeading(fn ($record) => 'Tes koneksi ke '.$record->name.'?')
                    ->modalDescription('Memanggil cek saldo + daftar produk (read-only, tanpa order). Hasil dicatat di audit log.')
                    ->action(function ($record) {
                        $result = app(\App\Services\SupplierConnectionTester::class)->test($record);

                        $notification = Notification::make()
                            ->title($result['ok'] ? 'Koneksi berhasil' : 'Koneksi gagal')
                            ->body($result['message']);

                        if ($result['ok']) {
                            $notification->success();
                        } else {
                            $notification->danger();
                        }

                        $notification->send();

                        $this->dispatch('$refresh');
                    }),
                Action::make('viewTestLog')
                    ->label('Lihat Log')
                    ->icon(Heroicon::OutlinedDocumentText)
                    ->color('gray')
                    ->visible(fn ($record) => $record->last_test_at !== null)
                    ->infolist(fn ($record) => [
                        TextEntry::make('status')
                            ->label('Hasil')
                            ->default($record->last_test_ok ? 'Berhasil' : 'Gagal')
                            ->badge()
                            ->color($record->last_test_ok ? 'success' : 'danger'),
                        TextEntry::make('waktu')
                            ->label('Waktu tes')
                            ->default($record->last_test_at?->format('d M Y H:i:s')),
                        TextEntry::make('ringkasan')
                            ->label('Ringkasan')
                            ->default($record->last_test_summary ?? '-'),
                        CodeEntry::make('log')
                            ->label('Logs (rahasia sudah disamarkan)')
                            ->default($record->last_test_log ?? '-')
                            ->copyable(),
                    ])
                    ->modalHeading(fn ($record) => 'Log tes koneksi: '.$record->name)
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Tutup'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
