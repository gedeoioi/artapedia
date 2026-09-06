<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\SupplierConfig;
use App\Models\Transaction;
use App\Models\User;
use App\Services\OrderService;
use App\Suppliers\VipResellerProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class VipResellerProviderTest extends TestCase
{
    use RefreshDatabase;

    protected function provider(): VipResellerProvider
    {
        return new VipResellerProvider(['api_id' => 'ID123', 'api_key' => 'KEY456'], true);
    }

    public function test_saldo_via_api_profile(): void
    {
        Http::fake(['vip-reseller.co.id/api/profile' => Http::response([
            'result' => true,
            'data' => ['full_name' => 'Tes', 'username' => 'tes', 'balance' => 250000, 'level' => 'Basic'],
            'message' => 'Successfully got your account details.',
        ], 200)]);

        $res = $this->provider()->getBalance();

        $this->assertTrue($res['result']);
        $this->assertEquals(250000, $res['data']['balance']);
        Http::assertSent(fn ($req) => $req->url() === 'https://vip-reseller.co.id/api/profile'
            && ($req->data()['sign'] ?? '') === md5('ID123KEY456'));
    }

    public function test_services_game_memakai_filter_game_status(): void
    {
        Http::fake(['vip-reseller.co.id/api/game-feature' => Http::response(['result' => true, 'data' => [
            ['code' => 'ML14-S14', 'game' => 'Mobile Legends A', 'name' => '14 Diamonds',
                'price' => ['basic' => 3310, 'premium' => 3260, 'special' => 3235],
                'description' => 'desc', 'server' => '1', 'status' => 'available'],
            ['code' => 'ML15', 'game' => 'Mobile Legends A', 'name' => '15 Diamonds',
                'price' => ['basic' => 4000, 'premium' => 3900, 'special' => 3800], 'status' => 'empty'],
        ]], 200)]);

        $res = $this->provider()->getProducts(['game' => 'Mobile Legends', 'status' => 'available']);

        $this->assertTrue($res['result']);
        $this->assertCount(1, $res['data']);
        $row = $res['data'][0];
        $this->assertEquals('ML14-S14', $row['code']);
        $this->assertEquals(3310, $row['price']);
        $this->assertEquals(3260, $row['price_premium']);
        $this->assertEquals(3235, $row['price_special']);
        Http::assertSent(fn ($req) => ($req->data()['filter_game'] ?? '') === 'Mobile Legends'
            && ($req->data()['filter_status'] ?? '') === 'available');
    }

    public function test_order_game_mengirim_data_zone_terpisah(): void
    {
        Http::fake(['vip-reseller.co.id/api/game-feature' => Http::response(['result' => true, 'data' => [
            'trxid' => 'VP123', 'data' => '136216325', 'zone' => '2685',
            'service' => 'ML B', 'status' => 'waiting', 'note' => '', 'balance' => 100000, 'price' => 195695,
        ], 'message' => 'Pesanan berhasil'], 200)]);

        $res = $this->provider()->order('ML100', '136216325', ['zone' => '2685']);

        $this->assertTrue($res['result']);
        $this->assertEquals('VP123', $res['data']['trxid']);
        Http::assertSent(function ($req) {
            $d = $req->data();

            return ($d['data_no'] ?? '') === '136216325'
                && ($d['data_zone'] ?? '') === '2685'
                && ! str_contains((string) ($d['data_no'] ?? ''), '|');
        });
    }

    public function test_check_status_mengambil_item_trxid_dari_array(): void
    {
        Http::fake(['vip-reseller.co.id/api/game-feature' => Http::response(['result' => true, 'data' => [
            ['trxid' => 'VP1', 'data' => '1', 'service' => 'X', 'status' => 'success', 'note' => 'SN1', 'price' => 1000],
            ['trxid' => 'VP2', 'data' => '2', 'service' => 'Y', 'status' => 'error', 'note' => 'Zone salah', 'price' => 2000],
        ]], 200)]);

        $ok = $this->provider()->checkStatus('VP1');
        $this->assertTrue($ok['result']);
        $this->assertEquals('success', $ok['status']);

        $fail = $this->provider()->checkStatus('VP2');
        $this->assertTrue($fail['result']);
        $this->assertEquals('failed', $fail['status']);
    }

    public function test_map_status_vip(): void
    {
        $this->assertEquals('success', VipResellerProvider::mapStatus('success'));
        $this->assertEquals('failed', VipResellerProvider::mapStatus('error'));
        $this->assertEquals('pending', VipResellerProvider::mapStatus('waiting'));
        $this->assertEquals('pending', VipResellerProvider::mapStatus('processing'));
    }

    public function test_verify_webhook_md5(): void
    {
        $p = $this->provider();
        $sig = md5('ID123KEY456');

        $this->assertTrue($p->verifyWebhook($sig));
        $this->assertFalse($p->verifyWebhook('salah'));
        $this->assertFalse($p->verifyWebhook(null));
    }

    public function test_check_nickname(): void
    {
        Http::fake(['vip-reseller.co.id/api/game-feature' => Http::response([
            'result' => true, 'data' => 'Itacimo', 'message' => 'Success.',
        ], 200)]);

        $res = $this->provider()->checkNickname('mobile-legends', '136216325', '2685');

        $this->assertTrue($res['result']);
        $this->assertEquals('Itacimo', $res['nickname']);
        Http::assertSent(fn ($req) => ($req->data()['type'] ?? '') === 'get-nickname'
            && ($req->data()['code'] ?? '') === 'mobile-legends'
            && ($req->data()['target'] ?? '') === '136216325'
            && ($req->data()['additional_target'] ?? '') === '2685');
    }

    public function test_check_nickname_dengan_region(): void
    {
        Http::fake(['vip-reseller.co.id/api/game-feature' => Http::response([
            'result' => true, 'data' => 'Itacimo',
            'country' => ['code' => 'ID', 'name' => 'Indonesia'], 'message' => 'Success.',
        ], 200)]);

        $res = $this->provider()->checkNickname('mobile-legends', '136216325', '2685');

        $this->assertEquals('Itacimo', $res['nickname']);
        $this->assertEquals('Indonesia', $res['country']['name']);
    }

    public function test_guess_nickname_code_dari_nama_game(): void
    {
        $this->assertEquals('mobile-legends', VipResellerProvider::guessNicknameCode('MOBILE LEGENDS'));
        $this->assertEquals('mobile-legends', VipResellerProvider::guessNicknameCode('Mobile Legends B'));
        $this->assertEquals('free-fire', VipResellerProvider::guessNicknameCode('Free Fire Max'));
        $this->assertEquals('pubgm', VipResellerProvider::guessNicknameCode('PUBG MOBILE'));
        $this->assertNull(VipResellerProvider::guessNicknameCode('Steam Wallet'));
        $this->assertTrue(VipResellerProvider::nicknameNeedsZone('mobile-legends'));
        $this->assertFalse(VipResellerProvider::nicknameNeedsZone('free-fire'));
    }

    public function test_webhook_vip_sukses_dan_duplicate_aman(): void
    {
        $config = SupplierConfig::create([
            'code' => 'vip-reseller', 'name' => 'VIP',
            'provider_class' => VipResellerProvider::class,
            'is_active' => true, 'is_sandbox' => true, 'priority' => 0,
            'credentials' => ['api_id' => 'ID123', 'api_key' => 'KEY456'],
        ]);
        $product = Product::create([
            'supplier_config_id' => $config->id, 'supplier_code' => 'ML100',
            'name' => 'ML 100', 'game' => 'Mobile Legends',
            'cost_basic' => 9000, 'cost_premium' => 9000, 'cost_special' => 9000,
            'price_guest' => 12000, 'price_biasa' => 11500, 'price_vip' => 11000,
            'is_active' => true, 'in_stock' => true,
        ]);
        $user = User::factory()->create(['balance' => 0, 'level' => 'biasa']);
        $trx = Transaction::create([
            'invoice_code' => 'INV-VIP-1', 'user_id' => $user->id, 'product_id' => $product->id,
            'supplier_config_id' => $config->id,
            'payment_gateway_code' => 'balance', 'target_user_id' => '136216325', 'target_zone' => '2685',
            'quantity' => 1, 'cost_price' => 9000, 'sell_price' => 11500,
            'admin_fee' => 0, 'gateway_fee' => 0, 'total_amount' => 11500, 'profit' => 2500,
            'payment_method' => 'balance', 'status' => 'processing', 'paid_at' => now(),
            'supplier_trx_id' => 'VP123', 'supplier_status' => 'waiting',
        ]);

        $payload = ['data' => ['trxid' => 'VP123', 'data' => '136216325', 'zone' => '2685',
            'service' => 'ML B', 'status' => 'success', 'note' => '', 'price' => 9000]];
        $headers = ['X-Client-Signature' => md5('ID123KEY456')];

        $this->postJson('/webhook/supplier/vip-reseller', $payload, $headers)->assertOk();
        $this->postJson('/webhook/supplier/vip-reseller', $payload, $headers)->assertOk();
        $this->assertEquals('success', $trx->fresh()->status);

        $this->postJson('/webhook/supplier/vip-reseller', $payload, ['X-Client-Signature' => 'salah'])
            ->assertStatus(401);
    }

    public function test_dispatch_vip_mengirim_zone_terpisah(): void
    {
        $config = SupplierConfig::create([
            'code' => 'vip-reseller', 'name' => 'VIP',
            'provider_class' => VipResellerProvider::class,
            'is_active' => true, 'is_sandbox' => true, 'priority' => 0,
            'credentials' => ['api_id' => 'ID123', 'api_key' => 'KEY456'],
        ]);
        $product = Product::create([
            'supplier_config_id' => $config->id, 'supplier_code' => 'ML100',
            'name' => 'ML 100', 'game' => 'Mobile Legends',
            'cost_basic' => 9000, 'cost_premium' => 9000, 'cost_special' => 9000,
            'price_guest' => 12000, 'price_biasa' => 11500, 'price_vip' => 11000,
            'is_active' => true, 'in_stock' => true,
        ]);
        $trx = Transaction::create([
            'invoice_code' => 'INV-VIP-2', 'product_id' => $product->id,
            'supplier_config_id' => $config->id,
            'payment_gateway_code' => 'balance', 'target_user_id' => '136216325', 'target_zone' => '2685',
            'quantity' => 1, 'cost_price' => 9000, 'sell_price' => 11500,
            'admin_fee' => 0, 'gateway_fee' => 0, 'total_amount' => 11500, 'profit' => 2500,
            'payment_method' => 'balance', 'status' => 'paid', 'paid_at' => now(),
        ]);

        Http::fake(['vip-reseller.co.id/api/game-feature' => Http::response(['result' => true, 'data' => [
            'trxid' => 'VP999', 'status' => 'waiting', 'price' => 9000,
        ]], 200)]);

        $result = app(OrderService::class)->dispatchToSupplier($trx->id);

        $this->assertEquals('VP999', $result->supplier_trx_id);
        Http::assertSent(fn ($req) => ($req->data()['data_zone'] ?? '') === '2685'
            && ($req->data()['data_no'] ?? '') === '136216325');
    }
}
