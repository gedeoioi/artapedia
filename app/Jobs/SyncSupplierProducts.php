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
            $code = $row['code'] ?? $row['buyer_sku_code'] ?? $row['service'] ?? $row['id'] ?? null;
            if (! $code) {
                continue;
            }
            $name = $row['name'] ?? $row['product_name'] ?? $row['service_name'] ?? $code;
            $game = $row['game'] ?? $row['brand'] ?? $row['category'] ?? $this->filters['game'] ?? $this->filters['brand'] ?? 'Lainnya';
            // VIPayment: price = {basic, premium, special} -> 3 tier modal sekaligus.
            $priceNode = $row['price'] ?? $row['harga'] ?? 0;
            if (is_array($priceNode)) {
                $price = (int) ($priceNode['basic'] ?? 0);
                $pricePremium = (int) ($priceNode['premium'] ?? $price);
                $priceSpecial = (int) ($priceNode['special'] ?? $price);
            } else {
                $price = (int) $priceNode;
                $pricePremium = (int) ($row['price_premium'] ?? $row['harga_premium'] ?? $price);
                $priceSpecial = (int) ($row['price_special'] ?? $row['harga_special'] ?? $price);
            }
            if (isset($row['price_basic'])) {
                $price = (int) $row['price_basic'];
            }
            if (isset($row['price_premium'])) {
                $pricePremium = (int) $row['price_premium'];
            }
            if (isset($row['price_special'])) {
                $priceSpecial = (int) $row['price_special'];
            }
            $status = strtolower((string) ($row['status'] ?? 'available'));
            $inStock = ! in_array($status, ['kosong', 'empty', 'off', 'nonaktif'], true);
            if (array_key_exists('in_stock', $row)) {
                $inStock = (bool) $row['in_stock'];
            }

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
                    'in_stock' => $inStock,
                    'is_active' => true,
                    'description' => $row['desc'] ?? null,
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
