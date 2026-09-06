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

    public function iconUrl(): ?string
    {
        if ($this->image_path) {
            return asset('storage/'.$this->image_path);
        }
        if ($this->gameIcon?->icon_path) {
            return asset('storage/'.$this->gameIcon->icon_path);
        }

        return null;
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
