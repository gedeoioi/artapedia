<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

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
        'idempotency_key',
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

    protected static function booted(): void
    {
        static::saving(function (self $trx): void {
            // Kunci idempotensi hanya boleh menahan satu order selama order itu
            // masih hidup. Kalau order sudah gagal/kedaluwarsa, kuncinya harus
            // dilepas — kalau tidak, unique index akan menolak pembeli yang mau
            // mencoba ulang pada jendela waktu yang sama, dan yang muncul adalah
            // error constraint, bukan order baru.
            if (! $trx->exists) {
                return;
            }

            $finalButNotSuccessful = in_array($trx->status, [self::STATUS_FAILED, self::STATUS_EXPIRED], true);
            $originalStatus = (string) $trx->getOriginal('status');

            if ($finalButNotSuccessful && $originalStatus !== $trx->status) {
                $trx->idempotency_key = null;
            }
        });
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

    /**
     * Nama metode pembayaran untuk pembeli.
     *
     * Dipakai halaman pembayaran dan cek invoice supaya keduanya tidak
     * menghitung label yang sama dengan cara berbeda (dan supaya nama vendor
     * gateway tidak bocor ke sisi publik).
     */
    public function paymentMethodLabel(): string
    {
        if ($this->payment_method === 'balance') {
            return 'Saldo Member';
        }

        if ($this->payment_method === 'manual') {
            return 'Transfer Manual';
        }

        $payload = $this->invoice?->payload ?? $this->payment_payload ?? [];
        $channel = strtolower((string) data_get($payload, 'Data.Channel'));
        $paymentName = trim((string) data_get($payload, 'Data.PaymentName'));

        if ($paymentName !== '') {
            return $paymentName;
        }

        $gateway = $this->payment_gateway_code ?: $this->payment_method;

        return match (true) {
            $channel === 'mpm', $channel === 'qris' => 'QRIS ('.$gateway.')',
            $channel !== '' => strtoupper($channel).' ('.$gateway.')',
            default => Str::headline((string) $gateway),
        };
    }

    /**
     * Label kolom tujuan sesuai tipe produk: game memakai "ID Game",
     * pulsa/data memakai "Nomor HP".
     */
    public function targetLabel(): string
    {
        if (($this->meta['kind'] ?? null) === 'topup') {
            return 'Jenis Transaksi';
        }

        if ($this->product?->product_type === Product::TYPE_GAME) {
            return 'ID Game';
        }

        return $this->product?->targetLabel() ?? 'ID / Nomor Tujuan';
    }

    /**
     * Waktu transaksi dalam WIB, siap tampil.
     */
    public function localCreatedAt(): ?string
    {
        return $this->created_at?->timezone('Asia/Jakarta')->format('d/m/Y, H:i');
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
                'waiting' => 'Pesanan sudah kami terima dan sedang dalam antrean.',
                'processing', 'proccessing' => 'Pesanan sedang kami proses.',
                default => 'Pembayaran diterima dan pesanan sedang kami proses.',
            };
        }

        return match ($this->status) {
            self::STATUS_PENDING => 'Menunggu pembayaran terdeteksi.',
            self::STATUS_PAID => 'Pembayaran diterima, pesanan segera kami proses.',
            self::STATUS_SUCCESS => 'Pesanan berhasil diselesaikan.',
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
        // Biaya gateway dibayar pelanggan sebagai bagian dari total, lalu
        // diteruskan ke provider. Karena itu biaya tersebut tidak boleh
        // mengurangi profit produk untuk kedua kalinya.
        $this->profit = $this->total_amount - $this->cost_price - $this->gateway_fee;
    }

    /**
     * Kunci idempotensi order: satu kombinasi (produk, tujuan, zone, quantity,
     * metode bayar) hanya boleh menghasilkan satu transaksi selama jendela
     * waktu tertentu. Tanpa ini, double-click / retry jaringan membuat dua
     * transaksi yang keduanya benar-benar mengirim kredit ke supplier dan tidak
     * bisa ditarik kembali.
     */
    public static function idempotencyKey(array $data, ?User $user = null): string
    {
        $payload = [
            'product_id' => (int) ($data['product_id'] ?? 0),
            'target' => trim((string) ($data['target_user_id'] ?? '')),
            'zone' => trim((string) ($data['target_zone'] ?? '')),
            'quantity' => max(1, (int) ($data['quantity'] ?? 1)),
            'gateway' => (string) ($data['gateway_code'] ?? 'balance'),
            'ipaymu_method' => (string) ($data['ipaymu_method'] ?? ''),
            'ipaymu_channel' => (string) ($data['ipaymu_channel'] ?? ''),
            'buyer' => $user?->id ?? trim((string) ($data['buyer_phone'] ?? '')),
            'window' => now()->format('YmdHi'),
        ];

        return hash('sha256', json_encode($payload, JSON_UNESCAPED_SLASHES));
    }

    /**
     * Transaksi identik yang masih hidup pada jendela idempotensi yang sama.
     * Transaksi final (gagal/expired) tidak dianggap duplikat supaya pembeli
     * tetap bisa mencoba ulang setelah kegagalan.
     */
    public static function findDuplicate(string $key): ?self
    {
        return static::where('idempotency_key', $key)
            ->whereIn('status', [self::STATUS_PENDING, self::STATUS_PAID, self::STATUS_PROCESSING, self::STATUS_SUCCESS])
            ->latest('id')
            ->first();
    }
}
