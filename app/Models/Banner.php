<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Banner extends Model
{
    protected $fillable = [
        'title', 'subtitle', 'image_path', 'link_url',
        'button_text', 'sort_order', 'is_active',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'sort_order' => 'integer'];
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
}
