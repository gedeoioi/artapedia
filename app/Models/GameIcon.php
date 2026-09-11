<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GameIcon extends Model
{
    protected $fillable = [
        'game_name',
        'slug',
        'icon_path',
        'is_active',
        'is_favorite',
        'favorite_order',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_favorite' => 'boolean',
            'favorite_order' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::deleting(function (GameIcon $category): void {
            // Kategori beranda dibentuk dari produk aktif. Saat kategori dihapus,
            // nonaktifkan semua produk child agar kategori benar-benar hilang,
            // tetapi pertahankan produknya demi invoice dan riwayat transaksi.
            Product::query()
                ->where(function ($query) use ($category): void {
                    $query->where('game_icon_id', $category->id)
                        ->orWhereRaw('LOWER(game) = ?', [mb_strtolower($category->game_name)]);
                })
                ->update([
                    'is_active' => false,
                    'in_stock' => false,
                ]);
        });
    }

    public function products()
    {
        return $this->hasMany(Product::class);
    }

    public function iconUrl(): ?string
    {
        if (! $this->icon_path) {
            return null;
        }
        if (str_starts_with($this->icon_path, 'http')) {
            return $this->icon_path;
        }

        return asset('storage/'.$this->icon_path);
    }
}
