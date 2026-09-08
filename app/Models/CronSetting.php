<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CronSetting extends Model
{
    public const KEY_POLL_PROCESSING = 'poll-processing-transactions';

    public const KEY_POLL_GATEWAY = 'poll-pending-gateway-invoices';

    public const KEY_EXPIRE = 'expire-overdue-invoices';

    public const KEY_SYNC_PRODUCTS = 'sync-supplier-products';

    public const DEFAULTS = [
        self::KEY_POLL_PROCESSING => ['label' => 'Polling status ke supplier (trx processing)', 'interval_minutes' => 1],
        self::KEY_POLL_GATEWAY => ['label' => 'Polling status bayar ke gateway (invoice pending)', 'interval_minutes' => 1],
        self::KEY_EXPIRE => ['label' => 'Auto-expire invoice kedaluwarsa', 'interval_minutes' => 1],
        self::KEY_SYNC_PRODUCTS => ['label' => 'Sinkronisasi harga produk supplier', 'interval_minutes' => 60],
    ];

    public const INTERVAL_OPTIONS = [
        1 => 'Tiap 1 menit',
        2 => 'Tiap 2 menit',
        5 => 'Tiap 5 menit',
        10 => 'Tiap 10 menit',
        15 => 'Tiap 15 menit',
        30 => 'Tiap 30 menit',
        60 => 'Tiap 1 jam',
        120 => 'Tiap 2 jam',
        360 => 'Tiap 6 jam',
        720 => 'Tiap 12 jam',
        1440 => 'Tiap 24 jam',
    ];

    protected $fillable = ['key', 'label', 'interval_minutes', 'is_active', 'last_run_at'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'interval_minutes' => 'integer',
            'last_run_at' => 'datetime',
        ];
    }

    public static function shouldRun(string $key): bool
    {
        $row = static::where('key', $key)->first();

        if (! $row || ! $row->is_active) {
            return false;
        }

        if (! $row->last_run_at) {
            return true;
        }

        return now()->greaterThanOrEqualTo($row->last_run_at->copy()->addMinutes(max($row->interval_minutes, 1)));
    }

    public static function markRan(string $key): void
    {
        static::where('key', $key)->update(['last_run_at' => now()]);
    }

    public static function seedDefaults(): void
    {
        foreach (static::DEFAULTS as $key => $def) {
            static::firstOrCreate(
                ['key' => $key],
                ['label' => $def['label'], 'interval_minutes' => $def['interval_minutes'], 'is_active' => true]
            );
        }
    }
}
