<?php

namespace Tests\Feature;

use App\Jobs\SyncSupplierProducts;
use App\Models\Product;
use App\Models\SupplierConfig;
use App\Models\User;
use App\Services\OrderService;
use App\Suppliers\DigiflazzProvider;
use App\Suppliers\VipResellerProvider;
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

    /**
     * Produk game untuk uji cek nickname.
     */
    protected function gameProduct(array $over = []): Product
    {
        return Product::create(array_merge([
            'supplier_code' => 'ML100', 'name' => 'ML 100 Diamond', 'game' => 'Mobile Legends',
            'product_type' => 'game',
            'cost_basic' => 10000, 'cost_premium' => 9500, 'cost_special' => 9000,
            'price_guest' => 12000, 'price_biasa' => 11500, 'price_vip' => 11000,
            'is_active' => true, 'in_stock' => true,
        ], $over));
    }

    /**
     * Produk game yang game-nya belum dipetakan ke kode nickname tidak punya
     * kotak cek sama sekali — cek otomatis tidak mungkin dijalankan untuknya.
     */
    public function test_game_tanpa_kode_nickname_tidak_menampilkan_kotak_cek(): void
    {
        $p = $this->gameProduct(['game' => 'Game Tidak Dikenal', 'nickname_check_code' => null]);

        $res = $this->get('/product/'.$p->id.'/checkout');

        $res->assertOk();
        $res->assertDontSee('id="nick-box"', false);
        $res->assertDontSee('id="btn-nick"', false);
        // User ID tetap diminta.
        $res->assertSee('id="target-uid"', false);
    }

    /**
     * Game yang punya kode nickname (baik dari mapping admin maupun tebakan nama
     * game) harus menampilkan kotak cek.
     */
    public function test_game_dengan_kode_nickname_menampilkan_kotak_cek(): void
    {
        $p = $this->gameProduct(['game' => 'Mobile Legends', 'nickname_check_code' => null]);

        $res = $this->get('/product/'.$p->id.'/checkout');

        $res->assertOk();
        $res->assertSee('id="nick-box"', false);
        $res->assertSee('id="btn-nick"', false);
    }

    public function test_kode_nickname_manual_di_admin_juga_didukung(): void
    {
        $p = $this->gameProduct(['game' => 'Game Tidak Dikenal', 'nickname_check_code' => 'free-fire']);

        $res = $this->get('/product/'.$p->id.'/checkout');

        $res->assertOk();
        $res->assertSee('id="nick-box"', false);
    }

    /**
     * Game yang butuh zone (Mobile Legends) tidak bisa dicek otomatis sebelum
     * zone diisi, jadi zone ditandai wajib — bukan "opsional" seperti sebelumnya.
     */
    public function test_game_yang_butuh_zone_menandai_zone_wajib(): void
    {
        $p = $this->gameProduct(['game' => 'Mobile Legends', 'nickname_check_code' => null]);

        $res = $this->get('/product/'.$p->id.'/checkout');

        $res->assertOk();
        $res->assertSee('Zone wajib diisi agar nickname bisa dicek otomatis.');
        $res->assertSee('Nickname terisi otomatis setelah User ID dan Zone diisi.');
        $res->assertDontSee('(opsional)', false);
    }

    /**
     * Game yang tidak butuh zone (Free Fire) tetap boleh dikosongkan, dan
     * petunjuknya tidak menyebut Zone.
     */
    public function test_game_tanpa_zone_menampilkan_opsional_dan_petunjuk_tanpa_zone(): void
    {
        $p = $this->gameProduct(['game' => 'Free Fire', 'nickname_check_code' => null]);

        $res = $this->get('/product/'.$p->id.'/checkout');

        $res->assertOk();
        $res->assertSee('(opsional)', false);
        $res->assertSee('Nickname terisi otomatis setelah User ID diisi.');
        $res->assertDontSee('Zone wajib diisi', false);
    }

    /**
     * Cek otomatis memakai endpoint yang sama dengan tombol manual, jadi
     * perilaku setelah ID diisi harus identik dengan menekan tombol.
     */
    public function test_endpoint_cek_tetap_sama_untuk_cek_otomatis(): void
    {
        // Pengecek nickname butuh minimal satu config VIP aktif.
        SupplierConfig::create([
            'code' => 'vip-reseller', 'name' => 'VIP',
            'provider_class' => VipResellerProvider::class,
            'is_active' => true, 'is_sandbox' => true, 'priority' => 0,
            'credentials' => ['api_id' => 'ID', 'api_key' => 'KEY'],
        ]);

        $p = $this->gameProduct(['game' => 'Free Fire', 'nickname_check_code' => null]);

        Http::fake(['vip-reseller.co.id/*' => Http::response([
            'result' => true, 'data' => 'FFAuto', 'message' => 'Success.',
        ], 200)]);

        // Cek otomatis mengirim request yang sama seperti tombol manual:
        // user_id sudah terisi, zone_id kosong (Free Fire tidak butuh zone).
        $res = $this->postJson('/checkout/check-nickname', [
            'product_id' => $p->id, 'user_id' => '12345', 'zone_id' => '',
        ]);

        $res->assertOk();
        $res->assertJson(['ok' => true, 'nickname' => 'FFAuto']);
    }
}
