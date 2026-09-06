<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $fillable = [
        'supplier_config_id',
        'game_icon_id',
        'supplier_code',
        'name',
        'game',
        'category',
        'nickname_check_code',
        'cost_basic',
        'cost_premium',
        'cost_special',
        'price_guest',
        'price_biasa',
        'price_vip',
        'is_active',
        'in_stock',
        'image_path',
        'description',
        'meta_title',
        'meta_description',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'in_stock' => 'boolean',
            'cost_basic' => 'integer',
            'cost_premium' => 'integer',
            'cost_special' => 'integer',
            'price_guest' => 'integer',
            'price_biasa' => 'integer',
            'price_vip' => 'integer',
        ];
    }

    public function supplier()
    {
        return $this->belongsTo(SupplierConfig::class, 'supplier_config_id');
    }

    public function gameIcon()
    {
        return $this->belongsTo(GameIcon::class, 'game_icon_id');
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }

    /**
     * Produk yang boleh tampil / dibeli customer:
     * aktif DAN stok tersedia.
     */
    public function scopeAvailable($query)
    {
        return $query->where('is_active', true)->where('in_stock', true);
    }

    public function iconUrl(): ?string
    {
        // 1. Override khusus produk ini (prioritas tertinggi).
        if ($this->image_path) {
            return $this->publicUrl($this->image_path);
        }
        // 2. Icon kategori via relasi (diisi otomatis saat sync).
        if ($this->gameIcon?->icon_path) {
            return $this->publicUrl($this->gameIcon->icon_path);
        }
        // 3. Fallback: cari icon kategori by NAMA game (untuk produk lama
        //    yang tersync sebelum relasi game_icon_id diisi).
        //    Pencocokan case-insensitive: "MOBILE LEGENDS" cocok dengan "Mobile Legends".
        $icon = GameIcon::where('game_name', $this->game)->where('is_active', true)->first()
            ?? GameIcon::whereRaw('LOWER(game_name) = ?', [mb_strtolower($this->game)])->where('is_active', true)->first();
        if ($icon?->icon_path) {
            return $this->publicUrl($icon->icon_path);
        }

        return null;
    }

    protected function publicUrl(string $path): string
    {
        if (str_starts_with($path, 'http')) {
            return $path;
        }

        return asset('storage/'.$path);
    }

    public function costForLevel(string $level): int
    {
        return match ($level) {
            'vip', 'admin' => (int) $this->cost_special,
            'biasa' => (int) $this->cost_premium,
            default => (int) $this->cost_basic,
        };
    }
}
