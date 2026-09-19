<?php

namespace App\Filament\Resources\Users\Tables;

use App\Models\BalanceMutation;
use App\Models\User;
use App\Services\BalanceService;
use App\Support\AdminRoles;
use App\Support\Rupiah;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('email')
                    ->label('Email address')
                    ->searchable(),
                TextColumn::make('email_verified_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('balance')
                    ->label('Saldo')
                    ->formatStateUsing(fn ($state) => Rupiah::format($state))
                    ->sortable(),
                TextColumn::make('level')
                    ->searchable(),
                TextColumn::make('status')
                    ->searchable(),
                TextColumn::make('phone')
                    ->searchable(),
                TextColumn::make('whatsapp')
                    ->searchable(),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('adjustBalance')
                    ->label('Sesuaikan saldo')
                    ->icon('heroicon-o-banknotes')
                    ->visible(fn (): bool => auth()->user()?->hasAdminPermission(AdminRoles::PERM_BALANCE) ?? false)
                    ->form([
                        Select::make('direction')
                            ->label('Arah')
                            ->options(['credit' => 'Tambah saldo', 'debit' => 'Kurangi saldo'])
                            ->required()
                            ->default('credit'),
                        TextInput::make('amount')
                            ->label('Jumlah')
                            ->numeric()
                            ->required()
                            ->minValue(1)
                            ->prefix('Rp'),
                        Textarea::make('reason')
                            ->label('Alasan (wajib, masuk audit trail)')
                            ->required()
                            ->rows(2)
                            ->maxLength(255),
                    ])
                    ->modalHeading('Penyesuaian saldo manual')
                    ->modalDescription('Perubahan tercatat sebagai baris ledger baru beserta siapa, kapan, dan alasannya. Ledger tidak pernah diubah atau dihapus.')
                    ->action(function (User $record, array $data): void {
                        $amount = (int) $data['amount'];
                        $actor = auth()->user();

                        try {
                            app(BalanceService::class)->adjust(
                                $record,
                                $data['direction'] === 'debit' ? -$amount : $amount,
                                BalanceMutation::TYPE_ADJUST,
                                $data['reason'],
                                null,
                                $actor?->id,
                            );
                        } catch (\Throwable $e) {
                            Notification::make()
                                ->title('Penyesuaian gagal')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();

                            return;
                        }

                        Notification::make()
                            ->title('Saldo disesuaikan')
                            ->body(Rupiah::format($record->fresh()->balance))
                            ->success()
                            ->send();
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
