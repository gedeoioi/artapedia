<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\SupplierConfig;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CheckoutNicknameTest extends TestCase
{
    use RefreshDatabase;

    protected function makeProduct(array $over = []): Product
    {
        $s = SupplierConfig::create([
            'code' => 'vip-reseller', 'name' => 'VIP',
            'provider_class' => \App\Suppliers\VipResellerProvider::class,
            'is_active' => true, 'is_sandbox' => true, 'priority' => 0,
            'credentials' => ['api_id' => 'ID', 'api_key' => 'KEY'],
        ]);

        return Product::create(array_merge([
            'supplier_config_id' => $s->id, 'supplier_code' => 'ML1',
            'name' => 'ML 1', 'game' => 'MOBILE LEGENDS',
            'cost_basic' => 100, 'cost_premium' => 100, 'cost_special' => 100,
            'price_guest' => 120, 'price_biasa' => 115, 'price_vip' => 110,
            'is_active' => true, 'in_stock' => true,
        ], $over));
    }

    protected function makeDigiflazzProduct(array $over = []): Product
    {
        $s = SupplierConfig::create([
            'code' => 'digiflazz', 'name' => 'Digiflazz',
            'provider_class' => \App\Suppliers\DigiflazzProvider::class,
            'is_active' => true, 'is_sandbox' => true, 'priority' => 1,
            'credentials' => ['username' => 'U', 'api_key' => 'K'],
        ]);
        // VIP tetap aktif sebagai pengecek nickname universal.
        SupplierConfig::create([
            'code' => 'vip-reseller', 'name' => 'VIP',
            'provider_class' => \App\Suppliers\VipResellerProvider::class,
            'is_active' => true, 'is_sandbox' => true, 'priority' => 0,
            'credentials' => ['api_id' => 'ID', 'api_key' => 'KEY'],
        ]);

        return Product::create(array_merge([
            'supplier_config_id' => $s->id, 'supplier_code' => 'ML100',
            'name' => 'ML 100', 'game' => 'MOBILE LEGENDS',
            'cost_basic' => 100, 'cost_premium' => 100, 'cost_special' => 100,
            'price_guest' => 120, 'price_biasa' => 115, 'price_vip' => 110,
            'is_active' => true, 'in_stock' => true,
        ], $over));
    }

    public function test_tebak_kode_otomatis_dari_nama_game(): void
    {
        $this->makeProduct(['nickname_check_code' => null]);

        Http::fake(['vip-reseller.co.id/*' => Http::response([
            'result' => true, 'data' => 'Itacimo', 'message' => 'Success.',
        ], 200)]);

        $res = $this->postJson('/checkout/check-nickname', [
            'product_id' => Product::first()->id, 'user_id' => '136216325', 'zone_id' => '2685',
        ]);

        $res->assertOk();
        $res->assertJson(['ok' => true, 'nickname' => 'Itacimo']);
        Http::assertSent(fn ($req) => ($req->data()['code'] ?? '') === 'mobile-legends');
    }

    public function test_zone_wajib_untuk_ml(): void
    {
        $this->makeProduct(['nickname_check_code' => null]);

        $res = $this->postJson('/checkout/check-nickname', [
            'product_id' => Product::first()->id, 'user_id' => '136216325', 'zone_id' => '',
        ]);

        $res->assertStatus(422);
        $res->assertJson(['ok' => false]);
    }

    public function test_mapping_manual_tetap_dipakai(): void
    {
        $this->makeProduct(['nickname_check_code' => 'free-fire', 'game' => 'Free Fire']);

        Http::fake(['vip-reseller.co.id/*' => Http::response([
            'result' => true, 'data' => 'FFNick', 'message' => 'Success.',
        ], 200)]);

        $res = $this->postJson('/checkout/check-nickname', [
            'product_id' => Product::first()->id, 'user_id' => '123',
        ]);

        $res->assertOk();
        $res->assertJson(['ok' => true, 'nickname' => 'FFNick']);
    }

    public function test_gagal_memberi_pesan_jelas(): void
    {
        $this->makeProduct(['nickname_check_code' => 'free-fire', 'game' => 'Free Fire']);

        Http::fake(['vip-reseller.co.id/*' => Http::response([
            'result' => false, 'message' => 'ID tidak ditemukan',
        ], 200)]);

        $res = $this->postJson('/checkout/check-nickname', [
            'product_id' => Product::first()->id, 'user_id' => '000',
        ]);

        $res->assertStatus(422);
        $this->assertStringContainsString('ID tidak ditemukan', $res->json('message'));
    }

    public function test_produk_digiflazz_dicek_via_vip(): void
    {
        $p = $this->makeDigiflazzProduct();

        Http::fake(['vip-reseller.co.id/*' => Http::response([
            'result' => true, 'data' => 'Itacimo', 'message' => 'Success.',
        ], 200)]);

        $res = $this->postJson('/checkout/check-nickname', [
            'product_id' => $p->id, 'user_id' => '136216325', 'zone_id' => '2685',
        ]);

        $res->assertOk();
        $res->assertJson(['ok' => true, 'nickname' => 'Itacimo', 'via' => 'vip-reseller']);
        Http::assertSent(fn ($req) => str_contains($req->url(), 'vip-reseller.co.id'));
    }

    public function test_tanpa_vip_aktif_pesan_jelas(): void
    {
        $s = SupplierConfig::create([
            'code' => 'digiflazz', 'name' => 'Digiflazz',
            'provider_class' => \App\Suppliers\DigiflazzProvider::class,
            'is_active' => true, 'is_sandbox' => true, 'priority' => 1,
            'credentials' => ['username' => 'U', 'api_key' => 'K'],
        ]);
        $p = Product::create([
            'supplier_config_id' => $s->id, 'supplier_code' => 'ML100',
            'name' => 'ML 100', 'game' => 'MOBILE LEGENDS',
            'cost_basic' => 100, 'cost_premium' => 100, 'cost_special' => 100,
            'price_guest' => 120, 'price_biasa' => 115, 'price_vip' => 110,
            'is_active' => true, 'in_stock' => true,
        ]);

        $res = $this->postJson('/checkout/check-nickname', [
            'product_id' => $p->id, 'user_id' => '136216325', 'zone_id' => '2685',
        ]);

        $res->assertStatus(422);
        $this->assertStringContainsString('VIPayment', $res->json('message'));
    }
}
