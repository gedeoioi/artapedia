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

    public function feeFor(int $amount, ?string $method = null, ?string $channel = null): int
    {
        $flat = (int) $this->fee_flat;
        $percent = (float) $this->fee_percent;
        $group = $method !== null ? ($this->channel_settings[$method] ?? null) : null;

        if ($this->code === 'ipaymu' && $channel !== null && is_array($group)) {
            $flat = array_key_exists('fee_flat', $group) ? (int) $group['fee_flat'] : $flat;
            $percent = array_key_exists('fee_percent', $group) ? (float) $group['fee_percent'] : $percent;
        }

        return $flat + (int) round($amount * $percent / 100);
    }
}
