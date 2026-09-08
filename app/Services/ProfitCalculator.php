<?php

namespace App\Services;

use App\Models\Product;
use App\Models\SiteSetting;

class ProfitCalculator
{
    public const MODE_PERCENT = 'percent';

    public const MODE_FLAT = 'flat';

    public const MODES = [
        self::MODE_PERCENT => 'Persentase',
        self::MODE_FLAT => 'Nominal flat',
    ];

    public function calculate(int $cost): int
    {
        if ($cost <= 0) {
            return 0;
        }

        if ($this->mode() === self::MODE_FLAT) {
            return $cost + max(0, (int) SiteSetting::get('profit_flat', 0));
        }

        $percent = max(0, (float) SiteSetting::get('profit_percent', 5));

        return (int) ceil($cost * (1 + $percent / 100));
    }

    public function mode(): string
    {
        $mode = (string) SiteSetting::get('profit_mode', self::MODE_PERCENT);

        return array_key_exists($mode, self::MODES) ? $mode : self::MODE_PERCENT;
    }

    public function applyToExistingProducts(): int
    {
        $updated = 0;

        Product::query()->chunkById(200, function ($products) use (&$updated): void {
            foreach ($products as $product) {
                $product->forceFill([
                    'price_guest' => $this->calculate((int) $product->cost_basic),
                    'price_biasa' => $this->calculate((int) $product->cost_premium),
                    'price_vip' => $this->calculate((int) $product->cost_special),
                ])->saveQuietly();
                $updated++;
            }
        });

        return $updated;
    }
}
