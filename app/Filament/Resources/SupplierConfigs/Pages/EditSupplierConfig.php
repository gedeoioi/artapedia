<?php

namespace App\Filament\Resources\SupplierConfigs\Pages;

use App\Filament\Resources\SupplierConfigs\SupplierConfigResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Infolists\Components\CodeEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class EditSupplierConfig extends EditRecord
{
    protected static string $resource = SupplierConfigResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('testConnection')
                ->label('Tes Koneksi')
                ->icon(Heroicon::OutlinedSignal)
                ->color('info')
                ->action(function () {
                    $result = app(\App\Services\SupplierConnectionTester::class)->test($this->record);
                    $this->record->refresh();

                    $notification = Notification::make()
                        ->title($result['ok'] ? 'Koneksi berhasil' : 'Koneksi gagal')
                        ->body($result['message']);

                    if ($result['ok']) {
                        $notification->success();
                    } else {
                        $notification->danger();
                    }

                    $notification->send();
                }),
            DeleteAction::make(),
        ];
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            $this->getFormContentComponent(),
            Section::make('Log Tes Koneksi Terakhir')
                ->description('Diperbarui setiap kali tombol Tes Koneksi diklik. Rahasia (api key/secret) disamarkan otomatis.')
                ->visible(fn () => $this->record->last_test_at !== null)
                ->schema([
                    TextEntry::make('last_test_ok')
                        ->label('Hasil')
                        ->formatStateUsing(fn ($state) => $state ? 'Berhasil' : 'Gagal')
                        ->badge()
                        ->color(fn ($state) => $state ? 'success' : 'danger'),
                    TextEntry::make('last_test_at')
                        ->label('Waktu')
                        ->dateTime('d M Y H:i:s'),
                    TextEntry::make('last_test_summary')
                        ->label('Ringkasan')
                        ->columnSpanFull(),
                    CodeEntry::make('last_test_log')
                        ->label('Logs')
                        ->copyable()
                        ->columnSpanFull(),
                ]),
        ]);
    }
}
