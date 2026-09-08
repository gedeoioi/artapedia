<?php

namespace App\Jobs;

use App\Models\AuditLog;
use App\Models\GameIcon;
use App\Models\Product;
use App\Models\SupplierConfig;
use App\Services\ProfitCalculator;
use App\Services\ProviderFactory;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Str;

class SyncSupplierProducts implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $supplierId, public array $filters = []) {}

    public function handle(): array
    {
        $supplier = SupplierConfig::findOrFail($this->supplierId);
        $provider = ProviderFactory::supplierFor($supplier);
        $res = $provider->getProducts($this->filters);

        if (! ($res['result'] ?? false)) {
            $message = (string) ($res['message'] ?? 'Gagal ambil produk dari supplier');
            AuditLog::record('products.sync_failed', $supplier, [], [
                'filters' => $this->filters,
                'message' => mb_substr($message, 0, 500),
            ]);

            return ['ok' => false, 'synced' => 0, 'message' => $message];
        }

        $rows = $res['data'] ?? [];
        if (! is_array($rows)) {
            $rows = [];
        }

        $count = 0;
        $profit = app(ProfitCalculator::class);
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $code = $row['code'] ?? $row['buyer_sku_code'] ?? $row['service'] ?? $row['id'] ?? null;
            if (! $code) {
                continue;
            }
            $name = $row['name'] ?? $row['product_name'] ?? $row['nama_produk'] ?? $row['nama'] ?? $row['service_name'] ?? $code;
            $game = $row['game'] ?? $row['operator_produk'] ?? $row['brand'] ?? $row['category'] ?? $this->filters['game'] ?? $this->filters['brand'] ?? 'Lainnya';
            // VIPayment: price = {basic, premium, special} -> 3 tier modal sekaligus.
            // TokoVoucher: price + price_gold/vip/vvip (dipetakan ke 3 tier modal).
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
            // TokoVoucher full: price_gold / price_vip / price_vvip.
            if (isset($row['price_gold']) || isset($row['price_vvip'])) {
                $price = (int) ($row['price_gold'] ?? $price);
                $pricePremium = (int) ($row['price_vip'] ?? $pricePremium);
                $priceSpecial = (int) ($row['price_vvip'] ?? $priceSpecial);
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
                    'category' => $row['category'] ?? 'game',
                    'product_type' => Product::detectType(
                        $row['category'] ?? '',
                        $game,
                        $row['type'] ?? '',
                        $name
                    ),
                    // Hubungkan ke icon kategori (by nama game persis).
                    // Ganti 1 icon di menu Game Icons -> semua produk kategori ini ikut berubah.
                    'game_icon_id' => GameIcon::firstOrCreate(
                        ['game_name' => $game],
                        ['slug' => Str::slug($game), 'is_active' => true]
                    )->id,
                    'cost_basic' => $price,
                    'cost_premium' => $pricePremium,
                    'cost_special' => $priceSpecial,
                    'price_guest' => $profit->calculate($price),
                    'price_biasa' => $profit->calculate($pricePremium),
                    'price_vip' => $profit->calculate($priceSpecial),
                    'in_stock' => $inStock,
                    'is_active' => true,
                    'description' => $row['desc'] ?? $row['deskripsi'] ?? null,
                ]
            );
            $count++;
        }

        $supplier->last_sync_at = now();
        $supplier->save();

        AuditLog::record('products.synced', $supplier, [], [
            'filters' => $this->filters,
            'synced' => $count,
        ]);

        return ['ok' => true, 'synced' => $count, 'message' => "Sync selesai: {$count} produk"];
    }
}
