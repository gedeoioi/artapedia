<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Banner extends Model
{
    protected $fillable = [
        'title', 'subtitle', 'image_path', 'link_url',
        'button_text', 'sort_order', 'duration_seconds', 'is_active',
    ];

    protected static function booted(): void
    {
        static::updated(function (Banner $banner): void {
            if ($banner->wasChanged('image_path')) {
                static::deleteLocalImage($banner->getOriginal('image_path'));
            }
        });

        static::deleted(fn (Banner $banner) => static::deleteLocalImage($banner->image_path));
    }

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'sort_order' => 'integer', 'duration_seconds' => 'integer'];
    }

    public function imageUrl(): ?string
    {
        if (! $this->image_path) {
            return null;
        }
        if (str_starts_with($this->image_path, 'http')) {
            return $this->image_path;
        }

        return asset('storage/'.$this->image_path);
    }

    public static function activeOrdered()
    {
        return static::where('is_active', true)->orderBy('sort_order')->orderByDesc('id')->get();
    }

    private static function deleteLocalImage(?string $path): void
    {
        if ($path && ! str_starts_with($path, 'http')) {
            Storage::disk('public')->delete($path);
        }
    }
}
