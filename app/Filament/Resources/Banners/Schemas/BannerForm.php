<?php

namespace App\Filament\Resources\Banners\Schemas;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class BannerForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('title')
                    ->label('Judul banner')
                    ->required()
                    ->maxLength(128)
                    ->placeholder('cth: Promo Topup ML Hemat 10%'),
                TextInput::make('subtitle')
                    ->label('Subjudul / deskripsi singkat')
                    ->maxLength(255)
                    ->columnSpanFull()
                    ->placeholder('cth: Berlaku sampai akhir bulan untuk semua nominal.'),
                FileUpload::make('image_path')
                    ->label('Gambar banner (lebar, misal 1200x400)')
                    ->image()
                    ->disk('public')
                    ->directory('banners')
                    ->visibility('public')
                    ->maxSize(3072)
                    ->columnSpanFull()
                    ->helperText('Disarankan landscape 1200x400 px. Kalau kosong, tampil kartu teks gradient.'),
                TextInput::make('link_url')
                    ->label('Link tujuan (opsional)')
                    ->url()
                    ->columnSpanFull()
                    ->placeholder('cth: https://... atau /game/Mobile%20Legends'),
                TextInput::make('button_text')
                    ->label('Teks tombol (opsional)')
                    ->placeholder('cth: Lihat Promo'),
                TextInput::make('sort_order')
                    ->label('Urutan tampil')
                    ->numeric()
                    ->default(0)
                    ->required()
                    ->helperText('Kecil = tampil duluan.'),
                Toggle::make('is_active')
                    ->label('Aktif')
                    ->required(),
            ]);
    }
}
