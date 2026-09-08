<?php

namespace App\Filament\Resources\GameIcons\Schemas;

use App\Models\Product;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class GameIconForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('game_name')
                    ->label('Nama kategori / game (cth: Mobile Legends)')
                    ->required()
                    ->helperText('Harus SAMA PERSIS dengan kolom "game" produk (huruf besar/kecil). Ganti 1 icon di sini -> semua produk kategori ini ikut berubah.'),
                TextInput::make('slug')
                    ->required(),
                FileUpload::make('icon_path')
                    ->label('Icon kategori')
                    ->image()
                    ->disk('public')
                    ->directory('game-icons')
                    ->visibility('public')
                    ->helperText('Upload 1 icon per kategori (bukan per varian). Berlaku ke semua produk child kategori ini, kecuali produk yang punya override sendiri.'),
                Toggle::make('is_active')
                    ->label('Kategori aktif')
                    ->default(true)
                    ->required(),
                Toggle::make('is_favorite')
                    ->label('Tampilkan di Kategori Favorit')
                    ->helperText('Aktifkan agar kategori muncul pada bagian favorit di halaman utama.')
                    ->live()
                    ->default(false),
                TextInput::make('favorite_order')
                    ->label('Urutan favorit')
                    ->helperText('Angka lebih kecil tampil lebih dahulu.')
                    ->numeric()
                    ->minValue(0)
                    ->default(0)
                    ->visible(fn ($get): bool => (bool) $get('is_favorite')),
                Placeholder::make('affected')
                    ->label('Produk yang akan ikut berubah')
                    ->content(fn ($record) => $record
                        ? Product::where('game', $record->game_name)->count().' produk bernama "'.$record->game_name.'"'
                        : 'Simpan dulu untuk melihat jumlah produk.'),
            ]);
    }
}
