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
