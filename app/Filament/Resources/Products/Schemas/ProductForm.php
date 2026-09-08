<?php

namespace App\Filament\Resources\Products\Schemas;

use App\Models\Product;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class ProductForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('supplier_config_id')
                    ->numeric(),
                TextInput::make('game_icon_id')
                    ->numeric(),
                TextInput::make('supplier_code')
                    ->required(),
                TextInput::make('name')
                    ->required(),
                TextInput::make('game')
                    ->required(),
                TextInput::make('category')
                    ->required()
                    ->default('game'),
                Select::make('product_type')
                    ->label('Tipe produk (menentukan form checkout)')
                    ->options(Product::TYPES)
                    ->required()
                    ->default('game'),
                TextInput::make('nickname_check_code')
                    ->label('Kode cek nickname (khusus game)'),
                TextInput::make('cost_basic')
                    ->required()
                    ->numeric()
                    ->prefix('Rp')
                    ->default(0),
                TextInput::make('cost_premium')
                    ->required()
                    ->numeric()
                    ->prefix('Rp')
                    ->default(0),
                TextInput::make('cost_special')
                    ->required()
                    ->numeric()
                    ->prefix('Rp')
                    ->default(0),
                TextInput::make('price_guest')
                    ->required()
                    ->numeric()
                    ->prefix('Rp')
                    ->default(0),
                TextInput::make('price_biasa')
                    ->required()
                    ->numeric()
                    ->prefix('Rp')
                    ->default(0),
                TextInput::make('price_vip')
                    ->required()
                    ->numeric()
                    ->prefix('Rp')
                    ->default(0),
                Toggle::make('is_active')
                    ->required(),
                Toggle::make('in_stock')
                    ->required(),
                FileUpload::make('image_path')
                    ->image(),
                Textarea::make('description')
                    ->columnSpanFull(),
                TextInput::make('meta_title'),
                Textarea::make('meta_description')
                    ->columnSpanFull(),
            ]);
    }
}
