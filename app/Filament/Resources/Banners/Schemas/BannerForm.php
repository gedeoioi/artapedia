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
                    ->label('Gambar banner')
                    ->image()
                    ->imageEditor()
                    ->imageEditorAspectRatioOptions(['3:1'])
                    ->imageAspectRatio('3:1')
                    ->automaticallyCropImagesToAspectRatio()
                    ->automaticallyResizeImagesMode('cover')
                    ->automaticallyResizeImagesToWidth('1200')
                    ->automaticallyResizeImagesToHeight('400')
                    ->automaticallyUpscaleImagesWhenResizing(false)
                    ->disk('public')
                    ->directory('banners')
                    ->visibility('public')
                    ->maxSize(3072)
                    ->columnSpanFull()
                    ->helperText('Otomatis dipotong ke rasio 3:1 dan diperkecil maksimal 1200x400 px sebelum diunggah. Maksimal file awal 3 MB.'),
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
                TextInput::make('duration_seconds')
                    ->label('Lama tampil per slide (detik)')
                    ->numeric()
                    ->minValue(2)
                    ->maxValue(60)
                    ->default(5)
                    ->required()
                    ->helperText('Berapa detik banner ini tampil sebelum geser otomatis.'),
                Toggle::make('is_active')
                    ->label('Aktif')
                    ->required(),
            ]);
    }
}
