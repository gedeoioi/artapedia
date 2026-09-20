<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WaMessage extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_SENT = 'sent';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'transaction_id', 'kind', 'phone', 'message',
        'status', 'attempts', 'http_status', 'response', 'error', 'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'attempts' => 'integer',
            'http_status' => 'integer',
            'sent_at' => 'datetime',
        ];
    }

    public function transaction()
    {
        return $this->belongsTo(Transaction::class);
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_SENT => 'Terkirim',
            self::STATUS_FAILED => 'Gagal',
            default => 'Menunggu',
        };
    }
}
