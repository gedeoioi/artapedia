<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupplierConfig extends Model
{
    protected $fillable = [
        'code',
        'name',
        'provider_class',
        'is_active',
        'is_sandbox',
        'priority',
        'credentials',
        'cached_balance',
        'last_sync_at',
        'last_test_at',
        'last_test_ok',
        'last_test_summary',
        'last_test_log',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_sandbox' => 'boolean',
            'credentials' => 'encrypted:array',
            'last_sync_at' => 'datetime',
            'cached_balance' => 'integer',
            'last_test_at' => 'datetime',
            'last_test_ok' => 'boolean',
        ];
    }

    public function products()
    {
        return $this->hasMany(Product::class);
    }

    public static function activeOrdered()
    {
        return static::where('is_active', true)->orderBy('priority')->get();
    }
}
