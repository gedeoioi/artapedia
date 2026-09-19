<?php

namespace App\Filament\Resources\Ratings\Tables;

use App\Models\Rating;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class RatingsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('stars')
                    ->label('Bintang')
                    ->formatStateUsing(fn (int $state): string => str_repeat('★', $state).str_repeat('☆', 5 - $state))
                    ->color(fn (int $state): string => $state >= 4 ? 'success' : ($state === 3 ? 'warning' : 'danger'))
                    ->sortable(),
                TextColumn::make('comment')
                    ->label('Komentar')
                    ->wrap()
                    ->placeholder('Tanpa komentar')
                    ->limit(120),
                TextColumn::make('moderation_note')
                    ->label('Catatan filter')
                    ->badge()
                    ->color('danger')
                    ->placeholder('-')
                    ->toggleable(),
                TextColumn::make('transaction.invoice_code')
                    ->label('Invoice')
                    ->fontFamily('mono')
                    ->searchable(),
                TextColumn::make('transaction.product.name')
                    ->label('Produk')
                    ->placeholder('Topup saldo')
                    ->limit(30),
                TextColumn::make('transaction.target_user_id')
                    ->label('Tujuan')
                    ->searchable(),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (Rating $record): string => $record->statusLabel())
                    ->color(fn (string $state): string => match ($state) {
                        Rating::STATUS_APPROVED => 'success',
                        Rating::STATUS_HIDDEN => 'danger',
                        default => 'warning',
                    }),
                TextColumn::make('created_at')
                    ->label('Dikirim')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options(Rating::STATUSES)
                    ->default(Rating::STATUS_PENDING),
            ])
            ->recordActions([
                Action::make('approve')
                    ->label('Tampilkan')
                    ->color('success')
                    ->icon('heroicon-o-check-circle')
                    ->visible(fn (Rating $record): bool => $record->status !== Rating::STATUS_APPROVED)
                    ->requiresConfirmation()
                    ->modalDescription('Rating ini akan tampil di beranda dan halaman produk.')
                    ->action(function (Rating $record): void {
                        $record->forceFill([
                            'status' => Rating::STATUS_APPROVED,
                            'moderation_note' => null,
                        ])->save();

                        Notification::make()->title('Rating ditampilkan')->success()->send();
                    }),

                Action::make('hide')
                    ->label('Sembunyikan')
                    ->color('danger')
                    ->icon('heroicon-o-eye-slash')
                    ->visible(fn (Rating $record): bool => $record->status !== Rating::STATUS_HIDDEN)
                    ->form([
                        Textarea::make('note')
                            ->label('Alasan (internal, tidak tampil ke pembeli)')
                            ->rows(2)
                            ->maxLength(255),
                    ])
                    ->action(function (Rating $record, array $data): void {
                        $record->forceFill([
                            'status' => Rating::STATUS_HIDDEN,
                            'moderation_note' => $data['note'] ?? $record->moderation_note,
                        ])->save();

                        Notification::make()->title('Rating disembunyikan')->success()->send();
                    }),
            ]);
    }
}
