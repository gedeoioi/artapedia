<?php

namespace App\Filament\Resources\Transactions\Tables;

use App\Models\Transaction;
use App\Services\OrderService;
use App\Support\Rupiah;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class TransactionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('invoice_code')
                    ->searchable(),
                TextColumn::make('user_id')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('product_id')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('supplier_config_id')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('payment_gateway_code')
                    ->searchable(),
                TextColumn::make('target_user_id')
                    ->searchable(),
                TextColumn::make('target_zone')
                    ->searchable(),
                TextColumn::make('nickname')
                    ->searchable(),
                TextColumn::make('quantity')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('cost_price')
                    ->formatStateUsing(fn ($state) => Rupiah::format($state))
                    ->sortable(),
                TextColumn::make('sell_price')
                    ->formatStateUsing(fn ($state) => Rupiah::format($state))
                    ->sortable(),
                TextColumn::make('admin_fee')
                    ->formatStateUsing(fn ($state) => Rupiah::format($state))
                    ->sortable(),
                TextColumn::make('gateway_fee')
                    ->formatStateUsing(fn ($state) => Rupiah::format($state))
                    ->sortable(),
                TextColumn::make('total_amount')
                    ->formatStateUsing(fn ($state) => Rupiah::format($state))
                    ->sortable(),
                TextColumn::make('profit')
                    ->formatStateUsing(fn ($state) => Rupiah::format($state))
                    ->sortable(),
                TextColumn::make('payment_method')
                    ->searchable(),
                TextColumn::make('payment_reference')
                    ->searchable(),
                TextColumn::make('status')
                    ->searchable(),
                TextColumn::make('supplier_orders_display')
                    ->label('Supplier trx id')
                    ->state(function (Transaction $record): array {
                        $orders = collect($record->payment_payload['supplier_orders'] ?? [])
                            ->map(function (array $order): string {
                                $index = $order['index'] ?? '?';
                                $trxId = $order['trxid'] ?? '-';
                                $status = $order['status'] ?? '-';

                                return "#{$index} {$trxId} ({$status})";
                            })
                            ->all();

                        return $orders !== [] ? $orders : [(string) ($record->supplier_trx_id ?: '-')];
                    })
                    ->listWithLineBreaks()
                    ->bulleted()
                    ->searchable(query: fn ($query, string $search) => $query->where(function ($query) use ($search): void {
                        $query->where('supplier_trx_id', 'like', "%{$search}%")
                            ->orWhere('payment_payload', 'like', "%{$search}%");
                    })),
                TextColumn::make('supplier_status')
                    ->searchable(),
                TextColumn::make('paid_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('processed_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('buyer_phone')
                    ->searchable(),
                TextColumn::make('buyer_email')
                    ->searchable(),
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
                Action::make('manualOrder')
                    ->label('Order manual')
                    ->requiresConfirmation()
                    ->action(fn ($record) => app(OrderService::class)->dispatchToSupplier($record->id, null, true)),
                Action::make('checkSupplierStatus')
                    ->label('Cek status supplier')
                    ->visible(fn ($record) => $record->status === 'processing' && filled($record->supplier_trx_id))
                    ->action(function ($record): void {
                        $transaction = app(OrderService::class)->pollStatus($record->id);

                        $notification = Notification::make()
                            ->title('Status supplier diperbarui')
                            ->body('Status transaksi: '.$transaction->status.'; supplier: '.($transaction->supplier_status ?: '-'));

                        $notification = match ($transaction->status) {
                            'success' => $notification->success(),
                            'failed' => $notification->danger(),
                            default => $notification->warning(),
                        };
                        $notification->send();
                    }),
                Action::make('refund')
                    ->label('Refund')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(fn ($record) => app(OrderService::class)->manualRefund($record->id)),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
