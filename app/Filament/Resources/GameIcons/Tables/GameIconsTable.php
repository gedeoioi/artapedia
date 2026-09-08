<?php

namespace App\Filament\Resources\GameIcons\Tables;

use App\Models\Product;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class GameIconsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('icon_path')
                    ->label('Icon')
                    ->disk('public')
                    ->square(),
                TextColumn::make('game_name')
                    ->label('Kategori / Game')
                    ->searchable(),
                TextColumn::make('products_count')
                    ->label('Produk child')
                    ->counts('products')
                    ->sortable(),
                IconColumn::make('is_active')
                    ->boolean(),
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
                Action::make('syncIcons')
                    ->label('Sinkronkan ke Produk')
                    ->icon(Heroicon::OutlinedArrowPath)
                    ->color('info')
                    ->requiresConfirmation()
                    ->modalHeading(fn ($record) => 'Hubungkan icon ke semua produk "'.$record->game_name.'"?')
                    ->modalDescription('Mengisi game_icon_id produk yang namanya sama persis + menghapus cache. Produk dengan override sendiri tidak diubah.')
                    ->action(function ($record) {
                        $updated = Product::where('game', $record->game_name)
                            ->whereNull('game_icon_id')
                            ->update(['game_icon_id' => $record->id]);

                        Notification::make()
                            ->title("{$updated} produk dihubungkan ke icon {$record->game_name}")
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
