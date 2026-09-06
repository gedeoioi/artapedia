<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\SupplierConfig;
use App\Models\Transaction;
use App\Models\User;
use App\Services\OrderService;
use App\Suppliers\TokoVoucherProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TokoVoucherProviderTest extends TestCase
{
    use RefreshDatabase;

    protected function provider(): TokoVoucherProvider
    {
        return new TokoVoucherProvider(['member_code' => 'MEM123', 'secret' => 'SCR123'], true);
    }

    protected function makeConfig(): SupplierConfig
    {
        return SupplierConfig::create([
            'code' => 'toko-voucher', 'name' => 'TokoVoucher',
            'provider_class' => TokoVoucherProvider::class,
            'is_active' => true, 'is_sandbox' => true, 'priority' => 2,
            'credentials' => ['member_code' => 'MEM123', 'secret' => 'SCR123'],
        ]);
    }

    public function test_sign_ref_sesuai_dokumentasi(): void
    {
        $this->assertEquals(md5('MEM123:SCR123:REF1'), $this->provider()->signRef('REF1'));
    }

    public function test_cek_saldo(): void
    {
        Http::fake(['api.tokovoucher.net/member*' => Http::response([
            'status' => 1, 'rc' => 200, 'message' => 'Detail member',
            'data' => ['saldo' => 1000, 'nama' => 'Dev', 'member_code' => 'MEM123'],
        ], 200)]);

        $res = $this->provider()->getBalance();

        $this->assertTrue($res['result']);
        $this->assertEquals(1000, $res['data']['balance']);
    }

    public function test_produk_full_dinormalisasi_dengan_logo_dan_3_tier(): void
    {
        Http::fake(['api.tokovoucher.net/member/produk/full*' => Http::response([
            'status' => 1, 'rc' => 200, 'message' => 'Data Found',
            'data' => [
                'category' => [['id' => 1, 'nama' => 'Topup Game']],
                'operator' => [['id' => 1, 'nama' => 'Free Fire', 'category_id' => 1, 'logo' => 'https://cdn/logo.png', 'status' => 1]],
                'jenis' => [['id' => 1, 'nama' => 'FF Fast', 'operator_id' => 1, 'status' => 1]],
                'produk' => [[
                    'id' => 1, 'kode_produk' => 'FF5', 'nama' => 'Free Fire 5 Diamond',
                    'deskripsi' => 'desc', 'price' => 1500, 'status' => 1,
                    'kategori_id' => 1, 'operator_id' => 1, 'jenis_id' => 1,
                    'price_gold' => 1500, 'price_vip' => 1490, 'price_vvip' => 1480,
                ]],
            ],
        ], 200)]);

        $res = $this->provider()->getProducts([]);

        $this->assertTrue($res['result']);
        $row = $res['data'][0];
        $this->assertEquals('FF5', $row['code']);
        $this->assertEquals('Free Fire', $row['game']);
        $this->assertEquals(1500, $row['price_gold']);
        $this->assertEquals(1490, $row['price_vip']);
        $this->assertEquals(1480, $row['price_vvip']);
        $this->assertEquals('https://cdn/logo.png', $row['logo']);
        $this->assertTrue($row['in_stock']);
    }

    public function test_search_produk_by_kode(): void
    {
        Http::fake(['api.tokovoucher.net/produk/code*' => Http::response([
            'status' => 1, 'rc' => 200, 'message' => 'Data Found',
            'data' => [['id' => 2, 'code' => 'FF5', 'category_name' => 'Voucher Game',
                'operator_produk' => 'Free Fire', 'jenis_name' => 'j', 'nama_produk' => 'FF 5',
                'deskripsi' => 'd', 'price' => 2500, 'status' => 1]],
        ], 200)]);

        $res = $this->provider()->getProducts(['game' => 'FF']);

        $this->assertTrue($res['result']);
        $this->assertEquals('FF5', $res['data'][0]['code']);
    }

    public function test_order_memakai_ref_id_stabil_dan_server_id(): void
    {
        Http::fake(['api.tokovoucher.net/v1/transaksi' => Http::response([
            'status' => 'pending', 'message' => 'PENDING', 'sn' => '',
            'ref_id' => 'TV-7', 'trx_id' => 'T220907X', 'produk' => 'FF5',
            'sisa_saldo' => 100, 'price' => 1600,
        ], 200)]);

        $res = $this->provider()->order('FF5', '123', ['trx_id' => 7, 'ref_id' => 'TV-7', 'server_id' => '2001']);

        $this->assertTrue($res['result']);
        $this->assertEquals('pending', $res['data']['status']);
        Http::assertSent(function ($req) {
            $d = $req->data();

            return ($d['ref_id'] ?? '') === 'TV-7'
                && ($d['signature'] ?? '') === md5('MEM123:SCR123:TV-7')
                && ($d['server_id'] ?? '') === '2001';
        });
    }

    public function test_timeout_dianggap_pending_bukan_gagal(): void
    {
        Http::fake(['api.tokovoucher.net/*' => function () {
            throw new \Exception('Connection timeout');
        }]);

        $res = $this->provider()->order('FF5', '123', ['ref_id' => 'TV-9']);

        $this->assertTrue($res['result']);
        $this->assertEquals('pending', $res['data']['status']);
    }

    public function test_check_status_memakai_ref_id(): void
    {
        Http::fake(['api.tokovoucher.net/v1/transaksi/status' => Http::response([
            'status' => 'sukses', 'message' => 'SUKSES', 'sn' => 'SN1',
            'ref_id' => 'TV-7', 'trx_id' => 'T220907X', 'produk' => 'FF5', 'price' => 789,
        ], 200)]);

        $res = $this->provider()->checkStatus('TV-7', ['ref_id' => 'TV-7']);

        $this->assertTrue($res['result']);
        $this->assertEquals('success', $res['status']);
    }

    public function test_verify_webhook(): void
    {
        $p = $this->provider();
        $sig = md5('MEM123:SCR123:TV-7');

        $this->assertTrue($p->verifyWebhook($sig, 'TV-7'));
        $this->assertFalse($p->verifyWebhook('salah', 'TV-7'));
        $this->assertFalse($p->verifyWebhook($sig, ''));
    }

    public function test_webhook_sukses_gagal_duplicate(): void
    {
        $config = $this->makeConfig();
        $product = Product::create([
            'supplier_config_id' => $config->id, 'supplier_code' => 'FF5',
            'name' => 'FF 5', 'game' => 'Free Fire',
            'cost_basic' => 1500, 'cost_premium' => 1490, 'cost_special' => 1480,
            'price_guest' => 1700, 'price_biasa' => 1650, 'price_vip' => 1600,
            'is_active' => true, 'in_stock' => true,
        ]);
        $user = User::factory()->create(['balance' => 0, 'level' => 'biasa']);
        $trx = Transaction::create([
            'invoice_code' => 'INV-TV-1', 'user_id' => $user->id, 'product_id' => $product->id,
            'supplier_config_id' => $config->id,
            'payment_gateway_code' => 'balance', 'target_user_id' => '123',
            'quantity' => 1, 'cost_price' => 1500, 'sell_price' => 1650,
            'admin_fee' => 0, 'gateway_fee' => 0, 'total_amount' => 1650, 'profit' => 150,
            'payment_method' => 'balance', 'status' => 'processing', 'paid_at' => now(),
            'supplier_trx_id' => 'TV-1', 'supplier_status' => 'pending',
        ]);

        $sig = md5('MEM123:SCR123:TV-1');
        $payload = ['ref_id' => 'TV-1', 'status' => 'sukses', 'sn' => 'SN1', 'trx_id' => 'T1', 'produk' => 'FF5', 'price' => 1500];

        $this->postJson('/webhook/supplier/toko-voucher', $payload, ['X-TokoVoucher-Authorization' => $sig])->assertOk();
        $this->postJson('/webhook/supplier/toko-voucher', $payload, ['X-TokoVoucher-Authorization' => $sig])->assertOk();
        $this->assertEquals('success', $trx->fresh()->status);

        $this->postJson('/webhook/supplier/toko-voucher', $payload, ['X-TokoVoucher-Authorization' => 'salah'])
            ->assertStatus(401);
    }

    public function test_dispatch_memakai_ref_id_tv_dan_server_id(): void
    {
        $config = $this->makeConfig();
        $product = Product::create([
            'supplier_config_id' => $config->id, 'supplier_code' => 'FF5',
            'name' => 'FF 5', 'game' => 'Free Fire',
            'cost_basic' => 1500, 'cost_premium' => 1490, 'cost_special' => 1480,
            'price_guest' => 1700, 'price_biasa' => 1650, 'price_vip' => 1600,
            'is_active' => true, 'in_stock' => true,
        ]);
        $trx = Transaction::create([
            'invoice_code' => 'INV-TV-2', 'product_id' => $product->id,
            'supplier_config_id' => $config->id,
            'payment_gateway_code' => 'balance', 'target_user_id' => '123', 'target_zone' => '2001',
            'quantity' => 1, 'cost_price' => 1500, 'sell_price' => 1650,
            'admin_fee' => 0, 'gateway_fee' => 0, 'total_amount' => 1650, 'profit' => 150,
            'payment_method' => 'balance', 'status' => 'paid', 'paid_at' => now(),
        ]);

        Http::fake(['api.tokovoucher.net/v1/transaksi' => Http::response([
            'status' => 'pending', 'ref_id' => 'TV-'.$trx->id, 'trx_id' => 'T1', 'price' => 1500,
        ], 200)]);

        $result = app(OrderService::class)->dispatchToSupplier($trx->id);

        $this->assertEquals('TV-'.$trx->id, $result->supplier_trx_id);
        Http::assertSent(fn ($req) => ($req->data()['ref_id'] ?? '') === 'TV-'.$trx->id
            && ($req->data()['server_id'] ?? '') === '2001');
    }
}
