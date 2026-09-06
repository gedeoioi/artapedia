<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentGatewayConfig extends Model
{
    protected $fillable = [
        'code',
        'name',
        'gateway_class',
        'is_active',
        'is_sandbox',
        'sort_order',
        'credentials',
        'fee_flat',
        'fee_percent',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_sandbox' => 'boolean',
            'credentials' => 'encrypted:array',
            'fee_flat' => 'integer',
            'fee_percent' => 'decimal:2',
        ];
    }

    public static function activeOrdered()
    {
        return static::where('is_active', true)->orderBy('sort_order')->get();
    }

    public function feeFor(int $amount): int
    {
        return (int) ($this->fee_flat + round($amount * ((float) $this->fee_percent) / 100));
    }
}
