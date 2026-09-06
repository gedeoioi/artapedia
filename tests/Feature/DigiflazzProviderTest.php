<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\SupplierConfig;
use App\Models\Transaction;
use App\Services\OrderService;
use App\Services\SupplierConnectionTester;
use App\Suppliers\DigiflazzProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DigiflazzProviderTest extends TestCase
{
    use RefreshDatabase;

    protected function makeConfig(): SupplierConfig
    {
        return SupplierConfig::create([
            'code' => 'digiflazz', 'name' => 'Digiflazz',
            'provider_class' => DigiflazzProvider::class,
            'is_active' => true, 'is_sandbox' => true, 'priority' => 1,
            'credentials' => ['username' => 'user1', 'api_key' => 'KEY1'],
        ]);
    }

    protected function provider(): DigiflazzProvider
    {
        return new DigiflazzProvider(['username' => 'user1', 'api_key' => 'KEY1'], true);
    }

    public function test_sign_sesuai_dokumentasi(): void
    {
        $p = $this->provider();

        $this->assertEquals(md5('user1KEY1depo'), $p->signDeposit());
        $this->assertEquals(md5('user1KEY1pricelist'), $p->signPricelist());
        $this->assertEquals(md5('user1KEY1REF123'), $p->signRef('REF123'));
    }

    public function test_cek_saldo(): void
    {
        Http::fake(['api.digiflazz.com/*' => Http::response(['data' => ['deposit' => 500000]], 200)]);

        $res = $this->provider()->getBalance();

        $this->assertTrue($res['result']);
        $this->assertEquals(500000, $res['data']['balance']);
        Http::assertSent(fn ($req) => ($req->data()['sign'] ?? '') === md5('user1KEY1depo'));
    }

    public function test_daftar_harga_dinormalisasi(): void
    {
        Http::fake(['api.digiflazz.com/*' => Http::response(['data' => [[
            'product_name' => 'ML 100 Diamond',
            'category' => 'Games', 'brand' => 'Mobile Legends', 'type' => 'Umum',
            'seller_name' => 'PT X', 'price' => 9500,
            'buyer_sku_code' => 'ML100', 'buyer_product_status' => true,
            'seller_product_status' => true, 'unlimited_stock' => true,
            'stock' => 0, 'desc' => 'desc produk',
        ]]], 200)]);

        $res = $this->provider()->getProducts([]);

        $this->assertTrue($res['result']);
        $row = $res['data'][0];
        $this->assertEquals('ML100', $row['code']);
        $this->assertEquals('Mobile Legends', $row['game']);
        $this->assertEquals(9500, $row['price']);
        $this->assertTrue($row['in_stock']);
    }

    public function test_order_memakai_ref_id_stabil_dan_testing_flag(): void
    {
        Http::fake(['api.digiflazz.com/*' => Http::response(['data' => [
            'ref_id' => 'AP-7', 'customer_no' => '123', 'buyer_sku_code' => 'ML100',
            'message' => 'Transaksi Pending', 'status' => 'Pending', 'rc' => '03',
            'price' => 9500,
        ]], 200)]);

        $res = $this->provider()->order('ML100', '123', ['trx_id' => 7, 'ref_id' => 'AP-7']);

        $this->assertTrue($res['result']);
        $this->assertEquals('AP-7', $res['data']['trxid']);
        Http::assertSent(function ($req) {
            $d = $req->data();

            return ($d['ref_id'] ?? '') === 'AP-7'
                && ($d['sign'] ?? '') === md5('user1KEY1AP-7')
                && ($d['testing'] ?? false) === true;
        });
    }

    public function test_check_status_memakai_ref_id_yang_sama(): void
    {
        Http::fake(['api.digiflazz.com/*' => Http::response(['data' => [
            'ref_id' => 'AP-7', 'status' => 'Sukses', 'rc' => '00', 'sn' => 'SN123',
        ]], 200)]);

        $res = $this->provider()->checkStatus('AP-7', ['buyer_sku_code' => 'ML100', 'customer_no' => '123']);

        $this->assertTrue($res['result']);
        $this->assertEquals('success', $res['status']);
        Http::assertSent(fn ($req) => ($req->data()['ref_id'] ?? '') === 'AP-7');
    }

    public function test_verify_webhook_hmac_sha1(): void
    {
        $p = new DigiflazzProvider(['username' => 'u', 'api_key' => 'k', 'webhook_secret' => 's3cr3t'], true);
        $raw = '{"data":{"ref_id":"AP-7","status":"Sukses"}}';
        $sig = 'sha1='.hash_hmac('sha1', $raw, 's3cr3t');

        $this->assertTrue($p->verifyWebhook($raw, $sig));
        $this->assertFalse($p->verifyWebhook($raw, 'sha1=salah'));
        $this->assertFalse($p->verifyWebhook($raw, null));
    }

    public function test_tes_koneksi_digiflazz_end_to_end(): void
    {
        $config = $this->makeConfig();

        Http::fake(['api.digiflazz.com/*' => Http::sequence()
            ->push(['data' => ['deposit' => 250000]], 200)
            ->push(['data' => [[
                'product_name' => 'P', 'category' => 'Games', 'brand' => 'B',
                'type' => 'Umum', 'seller_name' => 'S', 'price' => 1000,
                'buyer_sku_code' => 'SKU1', 'buyer_product_status' => true,
                'seller_product_status' => true, 'unlimited_stock' => true, 'stock' => 0,
            ]]], 200),
        ]);

        $result = app(SupplierConnectionTester::class)->test($config);

        $this->assertTrue($result['ok']);
        $this->assertEquals(250000, $config->fresh()->cached_balance);
    }

    public function test_dispatch_memakai_ref_id_ap_trx_id(): void
    {
        $config = $this->makeConfig();
        $product = Product::create([
            'supplier_config_id' => $config->id, 'supplier_code' => 'ML100',
            'name' => 'ML 100', 'game' => 'Mobile Legends',
            'cost_basic' => 9500, 'cost_premium' => 9500, 'cost_special' => 9500,
            'price_guest' => 12000, 'price_biasa' => 11500, 'price_vip' => 11000,
            'is_active' => true, 'in_stock' => true,
        ]);
        $trx = Transaction::create([
            'invoice_code' => 'INV-DGF-1', 'product_id' => $product->id,
            'supplier_config_id' => $config->id,
            'payment_gateway_code' => 'balance', 'target_user_id' => '123',
            'quantity' => 1, 'cost_price' => 9500, 'sell_price' => 11500,
            'admin_fee' => 0, 'gateway_fee' => 0, 'total_amount' => 11500, 'profit' => 2000,
            'payment_method' => 'balance', 'status' => 'paid', 'paid_at' => now(),
        ]);

        Http::fake(['api.digiflazz.com/*' => Http::response(['data' => [
            'ref_id' => 'AP-'.$trx->id, 'status' => 'Pending', 'rc' => '03', 'price' => 9500,
        ]], 200)]);

        $result = app(OrderService::class)->dispatchToSupplier($trx->id);

        $this->assertEquals('AP-'.$trx->id, $result->supplier_trx_id);
        Http::assertSent(fn ($req) => ($req->data()['ref_id'] ?? '') === 'AP-'.$trx->id);
    }
}
