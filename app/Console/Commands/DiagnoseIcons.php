<?php

namespace App\Console\Commands;

use App\Models\GameIcon;
use App\Models\Product;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class DiagnoseIcons extends Command
{
    protected $signature = 'icons:diagnose';
    protected $description = 'Cek kecocokan nama game vs game_icons + keberadaan file icon';

    public function handle(): int
    {
        $this->info('== GAME_ICONS ==');
        $icons = GameIcon::all(['game_name', 'icon_path', 'is_active']);
        if ($icons->isEmpty()) {
            $this->warn('(kosong — belum ada icon kategori)');
        }
        foreach ($icons as $i) {
            $exists = $i->icon_path && Storage::disk('public')->exists($i->icon_path) ? 'FILE-ADA' : 'FILE-HILANG';
            $this->line(($i->is_active ? '[on] ' : '[off] ').$i->game_name.' => '.$i->icon_path.' ('.$exists.')');
        }

        $this->info('== NAMA GAME DI PRODUK ==');
        $games = Product::select('game')->distinct()->orderBy('game')->limit(30)->pluck('game');
        if ($games->isEmpty()) {
            $this->warn('(kosong — belum ada produk)');
        }
        foreach ($games as $g) {
            // Simulasi persis logika beranda: cocok persis + aktif, lalu case-insensitive + aktif.
            $icon = GameIcon::where('game_name', $g)->where('is_active', true)->first()
                ?? GameIcon::whereRaw('LOWER(game_name) = ?', [mb_strtolower($g)])->where('is_active', true)->first();
            $count = Product::where('game', $g)->count();
            $linked = Product::where('game', $g)->whereNotNull('game_icon_id')->count();
            if (! $icon) {
                $this->line("[TIDAK-ADA-ICON] '$g' ($count produk, $linked terhubung) — buat baris game_icons bernama persis ini lalu upload icon");
                continue;
            }
            $fileOk = $icon->icon_path && Storage::disk('public')->exists($icon->icon_path);
            $url = $icon->iconUrl() ?? '-';
            $this->line(($fileOk ? '[OK] ' : '[FILE-HILANG] ')."'$g' -> {$icon->icon_path} ($url) [$count produk, $linked terhubung]");
        }

        $this->info('== SYMLINK ==');
        $this->line(is_link(public_path('storage')) ? 'OK: public/storage -> '.readlink(public_path('storage')) : 'RUSAK: public/storage bukan symlink — jalankan php artisan storage:link');

        return self::SUCCESS;
    }
}
