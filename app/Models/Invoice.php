<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    protected $fillable = [
        'transaction_id',
        'invoice_code',
        'gateway_code',
        'reference_id',
        'amount',
        'status',
        'expired_at',
        'paid_at',
        'payload',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'expired_at' => 'datetime',
            'paid_at' => 'datetime',
            'amount' => 'integer',
        ];
    }

    public function transaction()
    {
        return $this->belongsTo(Transaction::class);
    }
}
