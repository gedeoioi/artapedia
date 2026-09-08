<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_PAID = 'paid';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_SUCCESS = 'success';

    public const STATUS_FAILED = 'failed';

    public const STATUS_EXPIRED = 'expired';

    public const FINAL_STATUSES = [self::STATUS_SUCCESS, self::STATUS_FAILED, self::STATUS_EXPIRED];

    protected $fillable = [
        'invoice_code',
        'user_id',
        'product_id',
        'supplier_config_id',
        'payment_gateway_code',
        'target_user_id',
        'target_zone',
        'nickname',
        'quantity',
        'cost_price',
        'sell_price',
        'admin_fee',
        'gateway_fee',
        'total_amount',
        'profit',
        'payment_method',
        'payment_reference',
        'payment_payload',
        'status',
        'supplier_trx_id',
        'supplier_status',
        'paid_at',
        'processed_at',
        'buyer_phone',
        'buyer_email',
        'notes',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'meta' => 'array',
            'payment_payload' => 'array',
            'paid_at' => 'datetime',
            'processed_at' => 'datetime',
            'cost_price' => 'integer',
            'sell_price' => 'integer',
            'admin_fee' => 'integer',
            'gateway_fee' => 'integer',
            'total_amount' => 'integer',
            'profit' => 'integer',
            'quantity' => 'integer',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function supplier()
    {
        return $this->belongsTo(SupplierConfig::class, 'supplier_config_id');
    }

    public function invoice()
    {
        return $this->hasOne(Invoice::class);
    }

    public function rating()
    {
        return $this->hasOne(Rating::class);
    }

    public function isFinal(): bool
    {
        return in_array($this->status, self::FINAL_STATUSES, true);
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING => 'Menunggu pembayaran',
            self::STATUS_PAID => 'Pembayaran diterima',
            self::STATUS_PROCESSING => 'Sedang diproses',
            self::STATUS_SUCCESS => 'Transaksi berhasil',
            self::STATUS_FAILED => 'Transaksi gagal',
            self::STATUS_EXPIRED => 'Pembayaran kedaluwarsa',
            default => ucfirst((string) $this->status),
        };
    }

    public function statusMessage(): string
    {
        if ($this->status === self::STATUS_PROCESSING) {
            return match (strtolower((string) $this->supplier_status)) {
                'waiting' => 'Pesanan sudah diterima supplier dan sedang dalam antrean.',
                'processing', 'proccessing' => 'Pesanan sedang diproses oleh supplier.',
                default => 'Pembayaran diterima dan pesanan sedang diproses oleh supplier.',
            };
        }

        return match ($this->status) {
            self::STATUS_PENDING => 'Menunggu pembayaran terdeteksi.',
            self::STATUS_PAID => 'Pembayaran diterima, menunggu pengiriman pesanan ke supplier.',
            self::STATUS_SUCCESS => 'Pesanan berhasil diselesaikan oleh supplier.',
            self::STATUS_FAILED => 'Pesanan gagal diproses. Silakan hubungi layanan pelanggan.',
            self::STATUS_EXPIRED => 'Batas waktu pembayaran telah berakhir.',
            default => 'Status transaksi sedang diperbarui.',
        };
    }

    public function statusBadgeClass(): string
    {
        return match ($this->status) {
            self::STATUS_SUCCESS => 'badge-ok',
            self::STATUS_FAILED, self::STATUS_EXPIRED => 'badge-fail',
            default => 'badge-pending',
        };
    }

    public function recalculateProfit(): void
    {
        $this->profit = $this->sell_price - $this->cost_price - $this->gateway_fee;
    }
}
