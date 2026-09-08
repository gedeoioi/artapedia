<?php

namespace Tests\Feature;

use App\Jobs\SyncSupplierProducts;
use App\Models\Product;
use App\Models\SupplierConfig;
use App\Models\User;
use App\Services\OrderService;
use App\Suppliers\DigiflazzProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PulsaProductTest extends TestCase
{
    use RefreshDatabase;

    public function test_detect_type_pulsa_data_ppob(): void
    {
        $this->assertEquals('data', Product::detectType('Pulsa', 'TELKOMSEL', 'Umum', 'Telkomsel Data 10GB'));
        $this->assertEquals('pulsa', Product::detectType('Pulsa', 'XL', 'Umum', 'XL 25.000'));
        $this->assertEquals('pulsa', Product::detectType('', 'Telkomsel', '', 'Pulsa 5.000'));
        $this->assertEquals('ppob', Product::detectType('Pascabayar', 'PLN', '', 'PLN Postpaid'));
        $this->assertEquals('game', Product::detectType('Games', 'Mobile Legends', 'Umum', 'ML 100'));
        $this->assertEquals('voucher', Product::detectType('', '', '', 'Google Play IDR 20rb'));
    }

    public function test_sync_digiflazz_prepaid_mengisi_tipe_pulsa(): void
    {
        $s = SupplierConfig::create([
            'code' => 'digiflazz', 'name' => 'Digiflazz',
            'provider_class' => DigiflazzProvider::class,
            'is_active' => true, 'is_sandbox' => true, 'priority' => 1,
            'credentials' => ['username' => 'U', 'api_key' => 'K'],
        ]);

        Http::fake(['api.digiflazz.com/*' => Http::response(['data' => [[
            'product_name' => 'Telkomsel Pulsa 25.000', 'category' => 'Pulsa',
            'brand' => 'TELKOMSEL', 'type' => 'Umum', 'price' => 26000,
            'buyer_sku_code' => 'TSEL25', 'buyer_product_status' => true,
            'seller_product_status' => true,
        ]]], 200)]);

        $result = (new SyncSupplierProducts($s->id, ['cmd' => 'prepaid']))->handle();

        $this->assertTrue($result['ok']);
        $p = Product::first();
        $this->assertEquals('pulsa', $p->product_type);
        $this->assertEquals('Nomor HP', $p->targetLabel());
    }

    public function test_checkout_pulsa_nomor_tidak_valid_ditolak(): void
    {
        $p = Product::create([
            'supplier_code' => 'TSEL25', 'name' => 'TSEL 25', 'game' => 'TELKOMSEL',
            'product_type' => 'pulsa',
            'cost_basic' => 26000, 'cost_premium' => 26000, 'cost_special' => 26000,
            'price_guest' => 28000, 'price_biasa' => 27500, 'price_vip' => 27000,
            'is_active' => true, 'in_stock' => true,
        ]);
        $user = User::factory()->create(['balance' => 100000, 'level' => 'biasa']);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Nomor HP tidak valid');
        app(OrderService::class)->checkout([
            'product_id' => $p->id, 'target_user_id' => 'bukan-nomor', 'gateway_code' => 'balance',
        ], $user);
    }

    public function test_checkout_pulsa_nomor_valid_berhasil(): void
    {
        $p = Product::create([
            'supplier_code' => 'TSEL25', 'name' => 'TSEL 25', 'game' => 'TELKOMSEL',
            'product_type' => 'pulsa',
            'cost_basic' => 26000, 'cost_premium' => 26000, 'cost_special' => 26000,
            'price_guest' => 28000, 'price_biasa' => 27500, 'price_vip' => 27000,
            'is_active' => true, 'in_stock' => true,
        ]);
        $user = User::factory()->create(['balance' => 100000, 'level' => 'biasa']);

        $trx = app(OrderService::class)->checkout([
            'product_id' => $p->id, 'target_user_id' => '081234567890', 'gateway_code' => 'balance',
        ], $user);

        $this->assertEquals('081234567890', $trx->target_user_id);
    }

    public function test_halaman_checkout_pulsa_tanpa_nickname_dan_zone(): void
    {
        $p = Product::create([
            'supplier_code' => 'TSEL25', 'name' => 'TSEL 25', 'game' => 'TELKOMSEL',
            'product_type' => 'pulsa',
            'cost_basic' => 26000, 'cost_premium' => 26000, 'cost_special' => 26000,
            'price_guest' => 28000, 'price_biasa' => 27500, 'price_vip' => 27000,
            'is_active' => true, 'in_stock' => true,
        ]);

        $res = $this->get('/product/'.$p->id.'/checkout');

        $res->assertOk();
        $res->assertSee('Nomor HP', false);
        // Form zone + tombol nickname hanya untuk game (id tetap ada di JS tapi tidak dirender).
        $res->assertDontSee('id="zone"', false);
        $res->assertDontSee('id="btn-nick"', false);
    }
}
