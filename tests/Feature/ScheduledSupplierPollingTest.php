<?php

namespace Tests\Feature;

use App\Models\CronSetting;
use App\Models\Product;
use App\Models\SupplierConfig;
use App\Models\Transaction;
use App\Suppliers\VipResellerProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ScheduledSupplierPollingTest extends TestCase
{
    use RefreshDatabase;

    public function test_cron_memeriksa_supplier_langsung_tanpa_antrean_queue(): void
    {
        CronSetting::create([
            'key' => CronSetting::KEY_POLL_PROCESSING,
            'label' => 'Polling supplier',
            'interval_minutes' => 1,
            'is_active' => true,
        ]);
        $supplier = SupplierConfig::create([
            'code' => 'vip-reseller', 'name' => 'VIP',
            'provider_class' => VipResellerProvider::class,
            'is_active' => true, 'is_sandbox' => true, 'priority' => 0,
            'credentials' => ['api_id' => 'ID123', 'api_key' => 'KEY456'],
        ]);
        $product = Product::create([
            'supplier_config_id' => $supplier->id, 'supplier_code' => 'ML5',
            'name' => '5 Diamonds', 'game' => 'Mobile Legends', 'product_type' => Product::TYPE_GAME,
            'cost_basic' => 1000, 'cost_premium' => 1000, 'cost_special' => 1000,
            'price_guest' => 1200, 'price_biasa' => 1150, 'price_vip' => 1100,
            'is_active' => true, 'in_stock' => true,
        ]);
        $trx = Transaction::create([
            'invoice_code' => 'INV-CRON-POLL', 'product_id' => $product->id,
            'supplier_config_id' => $supplier->id,
            'payment_gateway_code' => 'balance', 'target_user_id' => '123456',
            'quantity' => 1, 'cost_price' => 1000, 'sell_price' => 1200,
            'admin_fee' => 0, 'gateway_fee' => 0, 'total_amount' => 1200, 'profit' => 200,
            'payment_method' => 'balance', 'status' => Transaction::STATUS_PROCESSING,
            'supplier_trx_id' => 'VP-CRON-1', 'supplier_status' => 'waiting', 'paid_at' => now(),
        ]);

        Http::fake(['vip-reseller.co.id/api/game-feature' => Http::response([
            'result' => true,
            'data' => [[
                'trxid' => 'VP-CRON-1', 'status' => 'success', 'note' => 'SN123', 'price' => 1000,
            ]],
        ])]);

        Artisan::call('schedule:test', ['--name' => 'poll-processing-transactions']);

        $this->assertSame(Transaction::STATUS_SUCCESS, $trx->fresh()->status);
        $this->assertSame('success', $trx->fresh()->supplier_status);
        $this->assertSame(0, DB::table('jobs')->count());
    }
}
