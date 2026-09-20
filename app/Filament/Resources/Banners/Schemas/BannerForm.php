<?php

namespace App\Filament\Resources\Banners\Schemas;

use App\Filament\Forms\Components\BannerImageUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;

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
                BannerImageUpload::make('image_path')
                    ->label('Gambar banner')
                    ->image()
                    ->imageEditor()
                    ->imageEditorAspectRatioOptions(['9:2'])
                    ->automaticallyResizeImagesMode('cover')
                    ->automaticallyResizeImagesToWidth('1152')
                    ->automaticallyResizeImagesToHeight('256')
                    ->automaticallyUpscaleImagesWhenResizing(false)
                    ->disk('public')
                    ->directory('banners')
                    ->visibility('public')
                    ->maxSize(3072)
                    ->columnSpanFull()
                    ->helperText(
                        'Ukuran saran: minimal 1152 x 256 px (rasio 9:2). '
                        .'Gambar yang lebih besar tidak masalah — otomatis diperkecil ke 1152 x 256 px. '
                        .'Gambar dipotong otomatis ke rasio 9:2, jadi tidak perlu dipotong sendiri. '
                        .'Maksimal file awal 3 MB. Format: JPG, PNG, atau WebP.'
                    ),
                Section::make('Bagaimana gambar ini tampil di tiap perangkat')
                    ->description('Banner memakai rasio 9:2 dengan tinggi minimum supaya tetap terbaca di layar sempit. Karena gambar 9:2 tidak bisa mengisi kotak yang lebih tinggi tanpa memotong sisi, sebagian kiri-kanan gambar terpotong di ponsel — lihat area aman di bawah.')
                    ->schema([
                        Placeholder::make('pratinjau_area_aman')
                            ->hiddenLabel()
                            ->content(new HtmlString(
                                '<div style="max-width:640px">'
                                // Diagram: bingkai 9:2 penuh, dengan area aman ponsel ditandai.
                                .'<div style="position:relative;aspect-ratio:9/2;border-radius:12px;overflow:hidden;'
                                .'background:linear-gradient(135deg,#fb923c,#ea580c);border:1px solid rgba(0,0,0,.15)">'
                                // Area yang terpotong di ponsel (kiri & kanan)
                                .'<div style="position:absolute;inset:0 0 0 0;background:repeating-linear-gradient(45deg,rgba(0,0,0,.28) 0 8px,rgba(0,0,0,.16) 8px 16px)"></div>'
                                // Area aman (tengah 52,7%) — bagian yang terlihat di ponsel
                                .'<div style="position:absolute;top:0;bottom:0;left:23.65%;width:52.7%;'
                                .'background:linear-gradient(135deg,#fdba74,#f97316);border-left:2px dashed rgba(255,255,255,.9);border-right:2px dashed rgba(255,255,255,.9);'
                                .'display:flex;align-items:center;justify-content:center">'
                                .'<span style="color:#7c2d12;font-size:.72rem;font-weight:700;text-align:center;line-height:1.3;padding:0 .4rem">AREA AMAN<br>terlihat di ponsel</span>'
                                .'</div>'
                                .'<span style="position:absolute;left:.5rem;top:.4rem;color:#fff;font-size:.65rem;font-weight:700;opacity:.85">terpotong</span>'
                                .'<span style="position:absolute;right:.5rem;top:.4rem;color:#fff;font-size:.65rem;font-weight:700;opacity:.85">terpotong</span>'
                                .'</div>'
                                .'<p style="margin:.5rem 0 0;font-size:.75rem;opacity:.75">Letakkan teks promo, harga, dan logo di dalam area aman. Bagian bergaris diagonal akan terpotong di layar di bawah 640 px.</p>'
                                .'</div>'
                            )),
                        Placeholder::make('rincian_perangkat')
                            ->hiddenLabel()
                            ->content(new HtmlString(
                                '<table style="width:100%;max-width:640px;font-size:.78rem;border-collapse:collapse">'
                                .'<thead><tr style="text-align:left">'
                                .'<th style="padding:.35rem .5rem;border-bottom:1px solid rgba(128,128,128,.3)">Perangkat</th>'
                                .'<th style="padding:.35rem .5rem;border-bottom:1px solid rgba(128,128,128,.3)">Lebar layar</th>'
                                .'<th style="padding:.35rem .5rem;border-bottom:1px solid rgba(128,128,128,.3)">Tinggi banner</th>'
                                .'<th style="padding:.35rem .5rem;border-bottom:1px solid rgba(128,128,128,.3)">Gambar terpotong</th>'
                                .'</tr></thead><tbody>'
                                .'<tr><td style="padding:.35rem .5rem"><strong>Ponsel</strong></td><td style="padding:.35rem .5rem">&lt; 640 px</td><td style="padding:.35rem .5rem">150 px</td><td style="padding:.35rem .5rem">~47% (kiri &amp; kanan)</td></tr>'
                                .'<tr><td style="padding:.35rem .5rem"><strong>Tablet</strong></td><td style="padding:.35rem .5rem">640 – 1023 px</td><td style="padding:.35rem .5rem">172 px</td><td style="padding:.35rem .5rem">~7%</td></tr>'
                                .'<tr><td style="padding:.35rem .5rem"><strong>Desktop</strong></td><td style="padding:.35rem .5rem">&ge; 1024 px</td><td style="padding:.35rem .5rem">217 – 250 px</td><td style="padding:.35rem .5rem">tidak ada</td></tr>'
                                .'</tbody></table>'
                                .'<p style="margin:.6rem 0 0;font-size:.75rem;opacity:.75">Ukuran gambar: 1152 &times; 256 px (rasio 9:2). Gambar lebih besar otomatis diperkecil ke ukuran itu, jadi tidak perlu disiapkan sendiri.</p>'
                            )),
                    ])
                    ->columnSpanFull(),
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
