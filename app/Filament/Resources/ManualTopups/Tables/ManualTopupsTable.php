<?php

namespace App\Filament\Resources\ManualTopups\Tables;

use App\Models\ManualTopup;
use App\Services\ManualTopupService;
use App\Support\Rupiah;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ManualTopupsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('code')
                    ->label('Kode')
                    ->searchable()
                    ->fontFamily('mono'),
                TextColumn::make('user.name')
                    ->label('Member')
                    ->description(fn (ManualTopup $record): string => (string) ($record->user->email ?? ''))
                    ->searchable(),
                TextColumn::make('amount')
                    ->label('Nominal')
                    ->formatStateUsing(fn ($state) => Rupiah::format($state))
                    ->sortable(),
                TextColumn::make('bank_name')
                    ->label('Bank tujuan')
                    ->description(fn (ManualTopup $record): string => trim($record->bank_account_number.' '.$record->bank_account_name))
                    ->searchable(),
                TextColumn::make('sender_name')
                    ->label('Pengirim')
                    ->placeholder('Belum diisi')
                    ->searchable(),
                ImageColumn::make('proof_path')
                    ->label('Bukti')
                    ->disk('public')
                    ->height(48)
                    ->width(48)
                    ->defaultImageUrl(null),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (ManualTopup $record): string => $record->statusLabel())
                    ->color(fn (string $state): string => match ($state) {
                        ManualTopup::STATUS_APPROVED => 'success',
                        ManualTopup::STATUS_REJECTED => 'danger',
                        default => 'warning',
                    }),
                TextColumn::make('reviewer.name')
                    ->label('Direview oleh')
                    ->placeholder('-'),
                TextColumn::make('review_note')
                    ->label('Catatan')
                    ->placeholder('-')
                    ->limit(40),
                TextColumn::make('created_at')
                    ->label('Diajukan')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        ManualTopup::STATUS_PENDING => 'Menunggu review',
                        ManualTopup::STATUS_APPROVED => 'Disetujui',
                        ManualTopup::STATUS_REJECTED => 'Ditolak',
                    ])
                    ->default(ManualTopup::STATUS_PENDING),
            ])
            ->recordActions([
                Action::make('proof')
                    ->label('Lihat bukti')
                    ->icon('heroicon-o-photo')
                    ->url(fn (ManualTopup $record): ?string => $record->proofUrl())
                    ->openUrlInNewTab()
                    ->visible(fn (ManualTopup $record): bool => filled($record->proof_path)),

                Action::make('approve')
                    ->label('Setujui')
                    ->color('success')
                    ->icon('heroicon-o-check-circle')
                    ->visible(fn (ManualTopup $record): bool => $record->isPending())
                    ->form([
                        Textarea::make('note')
                            ->label('Catatan (opsional)')
                            ->rows(2)
                            ->maxLength(255),
                    ])
                    ->modalHeading('Setujui topup manual')
                    ->modalDescription('Saldo member akan ditambah sebesar nominal ini. Tindakan ini tercatat di ledger dan tidak bisa dibatalkan.')
                    ->action(function (ManualTopup $record, array $data): void {
                        try {
                            app(ManualTopupService::class)->approve($record, auth()->user(), $data['note'] ?? null);
                        } catch (\RuntimeException $e) {
                            Notification::make()->title($e->getMessage())->danger()->send();

                            return;
                        }

                        Notification::make()
                            ->title('Topup disetujui, saldo member sudah ditambah')
                            ->success()
                            ->send();
                    }),

                Action::make('reject')
                    ->label('Tolak')
                    ->color('danger')
                    ->icon('heroicon-o-x-circle')
                    ->visible(fn (ManualTopup $record): bool => $record->isPending())
                    ->form([
                        Textarea::make('note')
                            ->label('Alasan penolakan')
                            ->rows(2)
                            ->required()
                            ->maxLength(255),
                    ])
                    ->modalHeading('Tolak topup manual')
                    ->action(function (ManualTopup $record, array $data): void {
                        try {
                            app(ManualTopupService::class)->reject($record, auth()->user(), $data['note'] ?? null);
                        } catch (\RuntimeException $e) {
                            Notification::make()->title($e->getMessage())->danger()->send();

                            return;
                        }

                        Notification::make()->title('Topup ditolak')->success()->send();
                    }),
            ]);
    }
}
