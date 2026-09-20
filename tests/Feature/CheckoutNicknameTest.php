<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\SiteSetting;
use App\Models\SupplierConfig;
use App\Suppliers\DigiflazzProvider;
use App\Suppliers\VipResellerProvider;
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
            'provider_class' => VipResellerProvider::class,
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
            'provider_class' => DigiflazzProvider::class,
            'is_active' => true, 'is_sandbox' => true, 'priority' => 1,
            'credentials' => ['username' => 'U', 'api_key' => 'K'],
        ]);
        // VIP tetap aktif sebagai pengecek nickname universal.
        SupplierConfig::create([
            'code' => 'vip-reseller', 'name' => 'VIP',
            'provider_class' => VipResellerProvider::class,
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
            'provider_class' => DigiflazzProvider::class,
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
        // Pesan harus menjelaskan ke pembeli tanpa menyebut nama vendor internal.
        $this->assertStringNotContainsStringIgnoringCase('vipayment', $res->json('message'));
        $this->assertStringNotContainsStringIgnoringCase('supplier', $res->json('message'));
        // Pesan lama "Layanan cek nickname sedang nonaktif." sebenarnya tidak akurat:
        // yang terjadi adalah config aktif tapi kredensialnya kosong.
        $this->assertStringContainsStringIgnoringCase('cek nickname', $res->json('message'));
        $this->assertStringContainsStringIgnoringCase('manual', $res->json('message'));
    }

    /**
     * Config yang AKTIF tapi kredensialnya kosong tetap tidak boleh dipakai.
     *
     * Inilah yang terjadi di server: is_active = 1, api_id & api_key kosong,
     * sehingga API dipanggil dan pembeli melihat pesan mentah dari vendor
     * ("Fails."). Tanpa pemeriksaan kredensial, pesan itu tidak bisa dicegah.
     */
    public function test_kredensial_kosong_dianggap_belum_siap(): void
    {
        $s = SupplierConfig::create([
            'code' => 'vip-reseller', 'name' => 'VIP',
            'provider_class' => VipResellerProvider::class,
            'is_active' => true, 'is_sandbox' => true, 'priority' => 0,
            'credentials' => ['api_id' => '', 'api_key' => ''],
        ]);
        $p = Product::create([
            'supplier_config_id' => $s->id, 'supplier_code' => 'ML1',
            'name' => 'ML 1', 'game' => 'MOBILE LEGENDS',
            'cost_basic' => 100, 'cost_premium' => 100, 'cost_special' => 100,
            'price_guest' => 120, 'price_biasa' => 115, 'price_vip' => 110,
            'is_active' => true, 'in_stock' => true,
        ]);

        // Tidak ada panggilan HTTP yang boleh terjadi.
        Http::fake();

        $res = $this->postJson('/checkout/check-nickname', [
            'product_id' => $p->id, 'user_id' => '301545936', 'zone_id' => '9575',
        ]);

        $res->assertStatus(422);
        Http::assertNothingSent();
        $this->assertStringContainsStringIgnoringCase('cek nickname', $res->json('message'));
        // Pesan mentah vendor tidak boleh bocor ke pembeli.
        $this->assertStringNotContainsString('Fails', $res->json('message'));
    }

    /**
     * Kegagalan transport (API tidak bisa dihubungi) bukan salah ID pemain,
     * jadi jangan menyuruh pembeli memeriksa User ID / Zone.
     */
    public function test_kegagalan_transport_tidak_menyalahkan_id_pemain(): void
    {
        $this->makeProduct(['nickname_check_code' => 'free-fire', 'game' => 'Free Fire']);

        Http::fake(['vip-reseller.co.id/*' => Http::response('', 500)]);

        $res = $this->postJson('/checkout/check-nickname', [
            'product_id' => Product::first()->id, 'user_id' => '123', 'zone_id' => '',
        ]);

        $res->assertStatus(422);
        $this->assertStringContainsStringIgnoringCase('tidak bisa dihubungi', $res->json('message'));
        $this->assertStringNotContainsString('Periksa User ID', $res->json('message'));
    }

    /**
     * Cek ulang ID yang sama tidak boleh memanggil API dua kali — endpointnya
     * berbayar, dan mengetik ulang adalah hal biasa.
     */
    public function test_hasil_cek_dipakai_ulang_dari_cache(): void
    {
        $this->makeProduct(['nickname_check_code' => 'free-fire', 'game' => 'Free Fire']);

        Http::fake(['vip-reseller.co.id/*' => Http::response([
            'result' => true, 'data' => 'FFNick', 'message' => 'Success.',
        ], 200)]);

        $payload = ['product_id' => Product::first()->id, 'user_id' => '555', 'zone_id' => ''];

        $this->postJson('/checkout/check-nickname', $payload)->assertOk()->assertJson(['nickname' => 'FFNick']);
        $this->postJson('/checkout/check-nickname', $payload)->assertOk()->assertJson(['nickname' => 'FFNick']);

        // Dua permintaan dari pembeli, satu panggilan ke API.
        Http::assertSentCount(1);
    }

    /**
     * Kegagalan tidak di-cache: kalau kredensial diperbaiki, percobaan
     * berikutnya harus benar-benar mencoba lagi, bukan menyajikan error lama.
     */
    public function test_kegagalan_tidak_di_cache(): void
    {
        $this->makeProduct(['nickname_check_code' => 'free-fire', 'game' => 'Free Fire']);

        Http::fake(['vip-reseller.co.id/*' => Http::response([
            'result' => false, 'message' => 'ID tidak ditemukan',
        ], 200)]);

        $payload = ['product_id' => Product::first()->id, 'user_id' => '777', 'zone_id' => ''];

        $this->postJson('/checkout/check-nickname', $payload)->assertStatus(422);
        $this->postJson('/checkout/check-nickname', $payload)->assertStatus(422);

        Http::assertSentCount(2);
    }

    public function test_cache_cek_nickname_bisa_dimatikan(): void
    {
        SiteSetting::set('nickname_cache_ttl', 0);

        $this->makeProduct(['nickname_check_code' => 'free-fire', 'game' => 'Free Fire']);

        Http::fake(['vip-reseller.co.id/*' => Http::response([
            'result' => true, 'data' => 'FFNick', 'message' => 'Success.',
        ], 200)]);

        $payload = ['product_id' => Product::first()->id, 'user_id' => '888', 'zone_id' => ''];

        $this->postJson('/checkout/check-nickname', $payload)->assertOk();
        $this->postJson('/checkout/check-nickname', $payload)->assertOk();

        Http::assertSentCount(2);
    }
}
