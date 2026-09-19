<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Rating extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_HIDDEN = 'hidden';

    public const STATUSES = [
        self::STATUS_PENDING => 'Menunggu moderasi',
        self::STATUS_APPROVED => 'Tampil publik',
        self::STATUS_HIDDEN => 'Disembunyikan',
    ];

    protected $fillable = ['transaction_id', 'user_id', 'stars', 'comment', 'status', 'moderation_note'];

    protected function casts(): array
    {
        return ['stars' => 'integer'];
    }

    public function transaction()
    {
        return $this->belongsTo(Transaction::class);
    }

    public function scopeApproved($query)
    {
        return $query->where('status', self::STATUS_APPROVED);
    }

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? ucfirst((string) $this->status);
    }
}
