<?php

namespace App\Jobs;

use App\Models\Product;
use App\Models\SupplierConfig;
use App\Services\ProviderFactory;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SyncSupplierProducts implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $supplierId, public array $filters = []) {}

    public function handle(): \App\Models\SupplierConfig
    {
        $supplier = SupplierConfig::findOrFail($this->supplierId);
        $provider = ProviderFactory::supplierFor($supplier);
        $res = $provider->getProducts($this->filters);

        $rows = $res['data'] ?? $res['services'] ?? $res ?? [];
        if (! is_array($rows)) {
            $rows = [];
        }

        $count = 0;
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $code = $row['code'] ?? $row['service'] ?? $row['id'] ?? null;
            if (! $code) {
                continue;
            }
            $name = $row['name'] ?? $row['service_name'] ?? $code;
            $game = $row['game'] ?? $row['category'] ?? $this->filters['game'] ?? 'Lainnya';
            $price = (int) ($row['price'] ?? $row['harga'] ?? 0);
            $pricePremium = (int) ($row['price_premium'] ?? $row['harga_premium'] ?? $price);
            $priceSpecial = (int) ($row['price_special'] ?? $row['harga_special'] ?? $price);
            $status = strtolower((string) ($row['status'] ?? 'available'));

            Product::updateOrCreate(
                ['supplier_config_id' => $supplier->id, 'supplier_code' => (string) $code],
                [
                    'name' => $name,
                    'game' => $game,
                    'cost_basic' => $price,
                    'cost_premium' => $pricePremium,
                    'cost_special' => $priceSpecial,
                    'price_guest' => $this->markup($price),
                    'price_biasa' => $this->markup($pricePremium),
                    'price_vip' => $this->markup($priceSpecial),
                    'in_stock' => ! in_array($status, ['kosong', 'empty', 'off', 'nonaktif'], true),
                    'is_active' => true,
                ]
            );
            $count++;
        }

        $supplier->last_sync_at = now();
        $supplier->save();

        return $supplier->fresh();
    }

    protected function markup(int $cost, float $pct = 5.0): int
    {
        return (int) ceil($cost * (1 + $pct / 100));
    }
}
