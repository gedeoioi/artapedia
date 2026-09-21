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
        'display_order',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_favorite' => 'boolean',
            'favorite_order' => 'integer',
            'display_order' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        // Kategori baru dari sync produk otomatis mendapat urutan berikutnya,
        // supaya admin tidak perlu mengisi satu per satu hanya agar kategori
        // baru tampil setelah kategori yang sudah diatur.
        static::creating(function (GameIcon $category): void {
            if (! $category->display_order) {
                $category->display_order = (int) static::max('display_order') + 1;
            }
        });

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
