<?php

namespace App\Filament\Resources\Products\Tables;

use App\Support\Rupiah;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ProductsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('supplier_config_id')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('game_icon_id')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('supplier_code')
                    ->searchable(),
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('game')
                    ->searchable(),
                TextColumn::make('category')
                    ->searchable(),
                TextColumn::make('nickname_check_code')
                    ->searchable(),
                TextColumn::make('cost_basic')
                    ->formatStateUsing(fn ($state) => Rupiah::format($state))
                    ->sortable(),
                TextColumn::make('cost_premium')
                    ->formatStateUsing(fn ($state) => Rupiah::format($state))
                    ->sortable(),
                TextColumn::make('cost_special')
                    ->formatStateUsing(fn ($state) => Rupiah::format($state))
                    ->sortable(),
                TextColumn::make('price_guest')
                    ->formatStateUsing(fn ($state) => Rupiah::format($state))
                    ->sortable(),
                TextColumn::make('price_biasa')
                    ->formatStateUsing(fn ($state) => Rupiah::format($state))
                    ->sortable(),
                TextColumn::make('price_vip')
                    ->formatStateUsing(fn ($state) => Rupiah::format($state))
                    ->sortable(),
                IconColumn::make('is_active')
                    ->boolean(),
                IconColumn::make('in_stock')
                    ->boolean(),
                ImageColumn::make('image_path'),
                TextColumn::make('meta_title')
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
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
