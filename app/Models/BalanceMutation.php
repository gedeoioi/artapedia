<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BalanceMutation extends Model
{
    public const TYPE_TOPUP = 'topup';
    public const TYPE_ORDER = 'order';
    public const TYPE_REFUND = 'refund';
    public const TYPE_ADJUST = 'adjust';

    protected $fillable = [
        'user_id',
        'transaction_id',
        'type',
        'amount',
        'balance_before',
        'balance_after',
        'description',
        'reference',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'balance_before' => 'integer',
            'balance_after' => 'integer',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function transaction()
    {
        return $this->belongsTo(Transaction::class);
    }
}
