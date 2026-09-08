<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

class SiteSetting extends Model
{
    public const DEFAULTS = [
        'site_name' => 'ArtaPedia',
        'site_description' => 'ArtaPedia: topup game, pulsa, dan PPOB cepat, aman, harga reseller.',
        'site_tagline' => 'Topup game, pulsa & PPOB',
        'logo_path' => null,
        'favicon_path' => null,
        'theme' => 'light',
        'primary_color' => '#f59e0b',
        'accent_color' => '#1a1a1a',
        'footer_text' => 'Topup game & PPOB.',
        'contact_whatsapp' => null,
        'contact_email' => null,
        'profit_mode' => 'percent',
        'profit_percent' => '5',
        'profit_flat' => '0',
    ];

    public const THEMES = [
        'light' => 'Terang (putih, flat)',
        'dark' => 'Gelap (flat)',
        'blue' => 'Biru flat',
        'green' => 'Hijau flat',
    ];

    protected $fillable = ['key', 'value'];

    public static function get(string $key, mixed $default = null): mixed
    {
        $value = Cache::rememberForever('site_setting.'.$key, function () use ($key) {
            return static::where('key', $key)->value('value');
        });

        if ($value === null) {
            return $default ?? static::DEFAULTS[$key] ?? null;
        }

        return $value;
    }

    public static function set(string $key, mixed $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => $value === null ? null : (string) $value]);
        Cache::forget('site_setting.'.$key);
    }

    public static function allKeyed(): array
    {
        return array_merge(
            static::DEFAULTS,
            static::query()->pluck('value', 'key')->toArray()
        );
    }

    public static function logoUrl(): ?string
    {
        $path = static::get('logo_path');
        if (! $path) {
            return null;
        }

        // File tersimpan di disk public (storage/app/public) -> URL /storage/...
        // Pakai disk public eksplisit agar tidak tergantung FILESYSTEM_DISK.
        if (Storage::disk('public')->exists($path)) {
            return Storage::disk('public')->url($path);
        }

        // Fallback: path absolut/URL penuh (misal dipindah manual ke public/).
        if (str_starts_with($path, 'http')) {
            return $path;
        }

        return asset('storage/'.$path);
    }

    public static function faviconUrl(): ?string
    {
        $path = static::get('favicon_path');
        if (! $path) {
            return null;
        }

        if (Storage::disk('public')->exists($path)) {
            return Storage::disk('public')->url($path);
        }

        if (str_starts_with($path, 'http')) {
            return $path;
        }

        return asset('storage/'.$path);
    }

    public static function theme(): string
    {
        return (string) (static::get('theme') ?: 'light');
    }
}
