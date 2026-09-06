<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    public const TYPE_GAME = 'game';
    public const TYPE_PULSA = 'pulsa';
    public const TYPE_DATA = 'data';
    public const TYPE_PPOB = 'ppob';
    public const TYPE_VOUCHER = 'voucher';
    public const TYPE_EMONEY = 'emoney';

    public const TYPES = [
        self::TYPE_GAME => 'Game',
        self::TYPE_PULSA => 'Pulsa',
        self::TYPE_DATA => 'Paket Data',
        self::TYPE_PPOB => 'PPOB',
        self::TYPE_VOUCHER => 'Voucher',
        self::TYPE_EMONEY => 'E-Money',
    ];

    protected $fillable = [
        'supplier_config_id',
        'game_icon_id',
        'supplier_code',
        'name',
        'game',
        'category',
        'product_type',
        'nickname_check_code',
        'input_schema',
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
            'input_schema' => 'array',
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

    /**
     * Produk yang boleh tampil / dibeli customer:
     * aktif DAN stok tersedia.
     */
    public function scopeAvailable($query)
    {
        return $query->where('is_active', true)->where('in_stock', true);
    }

    public function iconUrl(): ?string
    {
        // 1. Override khusus produk ini (prioritas tertinggi).
        if ($this->image_path) {
            return $this->publicUrl($this->image_path);
        }
        // 2. Icon kategori via relasi (diisi otomatis saat sync).
        if ($this->gameIcon?->icon_path) {
            return $this->publicUrl($this->gameIcon->icon_path);
        }
        // 3. Fallback: cari icon kategori by NAMA game (untuk produk lama
        //    yang tersync sebelum relasi game_icon_id diisi).
        //    Pencocokan case-insensitive: "MOBILE LEGENDS" cocok dengan "Mobile Legends".
        $icon = GameIcon::where('game_name', $this->game)->where('is_active', true)->first()
            ?? GameIcon::whereRaw('LOWER(game_name) = ?', [mb_strtolower($this->game)])->where('is_active', true)->first();
        if ($icon?->icon_path) {
            return $this->publicUrl($icon->icon_path);
        }

        return null;
    }

    protected function publicUrl(string $path): string
    {
        if (str_starts_with($path, 'http')) {
            return $path;
        }

        return asset('storage/'.$path);
    }

    public function costForLevel(string $level): int
    {
        return match ($level) {
            'vip', 'admin' => (int) $this->cost_special,
            'biasa' => (int) $this->cost_premium,
            default => (int) $this->cost_basic,
        };
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->product_type] ?? ucfirst((string) $this->product_type);
    }

    public function needsNicknameCheck(): bool
    {
        return $this->product_type === self::TYPE_GAME;
    }

    public function targetLabel(): string
    {
        return match ($this->product_type) {
            self::TYPE_PULSA, self::TYPE_DATA => 'Nomor HP',
            self::TYPE_PPOB, self::TYPE_EMONEY => 'Nomor Tujuan / ID Pelanggan',
            self::TYPE_VOUCHER => 'No. HP / Email (untuk kirim kode)',
            default => 'User ID',
        };
    }

    /**
     * Tebak tipe produk dari category/brand/type supplier.
     * Dipakai saat sync agar pulsa & paket data otomatis terpisah dari game.
     */
    public static function detectType(string $category, string $game, string $type, string $name): string
    {
        $hay = mb_strtolower(trim($category.' '.$game.' '.$type.' '.$name));

        $match = function (array $keywords) use ($hay): bool {
            foreach ($keywords as $kw) {
                if ($kw !== '' && str_contains($hay, $kw)) {
                    return true;
                }
            }

            return false;
        };

        if ($match(['paket data', 'paket internet', 'data ', 'internet', 'kuota'])) {
            return self::TYPE_DATA;
        }
        if ($match(['pulsa', 'pulsa reguler', 'pulsa transfer'])) {
            return self::TYPE_PULSA;
        }
        if ($match(['pln', 'token listrik', 'pascabayar', 'pdam', 'bpjs', 'pbb', 'tagihan', 'multifinance', 'ppob'])) {
            return self::TYPE_PPOB;
        }
        if ($match(['e-money', 'emoney', 'e money', 'saldo ', 'gopay', 'ovo', 'dana'])) {
            return self::TYPE_EMONEY;
        }
        if ($match(['voucher', 'gift', 'google play', 'steam wallet', 'playstation', 'xbox', 'itunes', 'razer', 'garena shell', 'megaxus'])) {
            return self::TYPE_VOUCHER;
        }

        return self::TYPE_GAME;
    }
}
