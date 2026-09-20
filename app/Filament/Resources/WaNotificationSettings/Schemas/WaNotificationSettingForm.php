<?php

namespace App\Filament\Resources\WaNotificationSettings\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class WaNotificationSettingForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Jenis notifikasi')
                    ->required()
                    ->disabled(fn (string $operation) => $operation === 'edit')
                    ->helperText('Nama teknis: trx_status (notifikasi pembeli), daily_recap (rekap harian), admin_alert (alert admin).'),
                Toggle::make('is_active')
                    ->label('Aktifkan notifikasi ini')
                    ->required()
                    ->helperText('Kalau mati, pesan jenis ini tidak dikirim — walau URL dan token sudah benar.'),
                Textarea::make('template')
                    ->label('Isi pesan')
                    ->columnSpanFull()
                    ->rows(3)
                    ->helperText('Bisa memakai: {nama} {invoice} {produk} {tujuan} {nominal} {status} {link} {tanggal} {situs}. Placeholder yang salah ketik dibiarkan apa adanya supaya terlihat di log.'),
                TextInput::make('api_url')
                    ->label('URL gateway WhatsApp')
                    ->url()
                    ->placeholder('https://api.deoioi.my.id')
                    ->helperText('Cukup host-nya saja (path /api/send-message ditambahkan otomatis), atau tulis lengkap dengan path.'),
                TextInput::make('api_token')
                    ->label('API key gateway')
                    ->password()
                    ->revealable()
                    ->autocomplete('new-password')
                    ->placeholder('wag_...')
                    ->helperText('Ambil dari dashboard gateway WA (menu API key). Dikirim sebagai header X-API-Key.')
                    ->columnSpanFull(),
                TextInput::make('recipient')
                    ->label('Nomor penerima (khusus admin_alert)')
                    ->placeholder('6281234567890, 6289876543210')
                    ->helperText('Pisahkan dengan koma. Untuk notifikasi pembeli, nomor diambil dari nomor WhatsApp yang diisi saat checkout.'),
                TextInput::make('schedule')
                    ->required()
                    ->default('on_event')
                    ->helperText('on_event = dikirim saat kejadian. Dipakai untuk rekap terjadwal.'),
                DateTimePicker::make('last_sent_at')
                    ->label('Terakhir terkirim')
                    ->disabled()
                    ->dehydrated(false),
            ]);
    }
}
