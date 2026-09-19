<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BalanceReconciliation extends Model
{
    protected $fillable = [
        'user_id',
        'ledger_total',
        'cached_balance',
        'difference',
        'unbacked_transactions',
        'action',
        'alerted',
    ];

    protected function casts(): array
    {
        return [
            'ledger_total' => 'integer',
            'cached_balance' => 'integer',
            'difference' => 'integer',
            'unbacked_transactions' => 'integer',
            'alerted' => 'boolean',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
