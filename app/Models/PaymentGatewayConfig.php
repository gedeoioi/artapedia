<?php

namespace App\Models;

use App\Payments\IPaymuGateway;
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
        'channel_settings',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_sandbox' => 'boolean',
            'credentials' => 'encrypted:array',
            'fee_flat' => 'integer',
            'fee_percent' => 'decimal:2',
            'channel_settings' => 'array',
        ];
    }

    public static function activeOrdered()
    {
        return static::where('is_active', true)->orderBy('sort_order')->get();
    }

    public function enabledCheckoutChannels(): array
    {
        $available = IPaymuGateway::CHECKOUT_CHANNELS;
        $settings = $this->channel_settings;

        // Konfigurasi lama tetap mengaktifkan seluruh channel sampai admin
        // menyimpan pengaturan channel untuk pertama kali.
        if ($settings === null) {
            return $available;
        }

        foreach ($available as $method => &$group) {
            $enabled = $settings[$method]['channels'] ?? [];
            $group['channels'] = array_intersect_key(
                $group['channels'],
                array_flip(is_array($enabled) ? $enabled : []),
            );
        }
        unset($group);

        return array_filter($available, fn (array $group): bool => $group['channels'] !== []);
    }

    public function isCheckoutChannelEnabled(?string $method, ?string $channel): bool
    {
        return $method !== null
            && $channel !== null
            && isset($this->enabledCheckoutChannels()[$method]['channels'][$channel]);
    }

    /**
     * Biaya layanan untuk satu transaksi.
     *
     * Tarif dipilih dari yang paling spesifik ke paling umum:
     *   1. tarif grup channel milik gateway ini (channel_settings[grup])
     *   2. tarif default gateway (fee_flat / fee_percent)
     *
     * $method adalah GRUP channel ('qris', 'va', 'ewallet', 'cstore'), bukan
     * kode channel gateway. Sebelumnya hanya iPaymu yang membaca tarif per
     * grup, sehingga Tripay selalu memakai tarif default — akibatnya memilih
     * QRIS atau Virtual Account menghasilkan biaya layanan yang sama persis.
     */
    public function feeFor(int $amount, ?string $method = null, ?string $channel = null): int
    {
        $flat = (int) $this->fee_flat;
        $percent = (float) $this->fee_percent;

        $group = $method !== null ? ($this->channel_settings[$method] ?? null) : null;

        if (is_array($group)) {
            if (array_key_exists('fee_flat', $group)) {
                $flat = (int) $group['fee_flat'];
            }

            if (array_key_exists('fee_percent', $group)) {
                $percent = (float) $group['fee_percent'];
            }
        }

        return $flat + (int) round($amount * $percent / 100);
    }

    /**
     * Apakah tarif per grup channel sudah diatur untuk gateway ini.
     *
     * Dipakai untuk membedakan "tarif grup memang nol" dari "belum diatur",
     * supaya pengingat konfigurasi hanya muncul saat memang perlu.
     */
    public function hasChannelRates(): bool
    {
        foreach ((array) $this->channel_settings as $group) {
            if (is_array($group) && (array_key_exists('fee_flat', $group) || array_key_exists('fee_percent', $group))) {
                return true;
            }
        }

        return false;
    }
}
