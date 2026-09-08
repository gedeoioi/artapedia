<?php

namespace Tests\Feature;

use App\Models\BalanceMutation;
use App\Models\Product;
use App\Models\SupplierConfig;
use App\Models\Transaction;
use App\Models\User;
use App\Suppliers\DigiflazzProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DigiflazzWebhookTest extends TestCase
{
    use RefreshDatabase;

    protected function makeTrx(array $over = []): Transaction
    {
        $config = SupplierConfig::create([
            'code' => 'digiflazz', 'name' => 'Digiflazz',
            'provider_class' => DigiflazzProvider::class,
            'is_active' => true, 'is_sandbox' => true, 'priority' => 1,
            'credentials' => ['username' => 'user1', 'api_key' => 'KEY1', 'webhook_secret' => 's3cr3t'],
        ]);
        $product = Product::create([
            'supplier_config_id' => $config->id, 'supplier_code' => 'ML100',
            'name' => 'ML 100', 'game' => 'Mobile Legends',
            'cost_basic' => 9500, 'cost_premium' => 9500, 'cost_special' => 9500,
            'price_guest' => 12000, 'price_biasa' => 11500, 'price_vip' => 11000,
            'is_active' => true, 'in_stock' => true,
        ]);
        $user = User::factory()->create(['balance' => 0, 'level' => 'biasa']);

        return Transaction::create(array_merge([
            'invoice_code' => 'INV-WH-1', 'user_id' => $user->id, 'product_id' => $product->id,
            'supplier_config_id' => $config->id,
            'payment_gateway_code' => 'balance', 'target_user_id' => '123',
            'quantity' => 1, 'cost_price' => 9500, 'sell_price' => 11500,
            'admin_fee' => 0, 'gateway_fee' => 0, 'total_amount' => 11500, 'profit' => 2000,
            'payment_method' => 'balance', 'status' => 'processing', 'paid_at' => now(),
            'supplier_trx_id' => 'AP-1', 'supplier_status' => 'Pending',
        ], $over));
    }

    protected function signed(array $data): array
    {
        $body = json_encode(['data' => $data]);
        $sig = 'sha1='.hash_hmac('sha1', $body, 's3cr3t');

        return [$body, $sig];
    }

    public function test_webhook_sukses_menyelesaikan_transaksi(): void
    {
        $trx = $this->makeTrx();
        [$body, $sig] = $this->signed(['ref_id' => 'AP-1', 'status' => 'Sukses', 'rc' => '00', 'sn' => 'SN1']);

        $res = $this->postJson('/webhook/supplier/digiflazz', json_decode($body, true), [
            'X-Hub-Signature' => $sig,
            'X-Digiflazz-Event' => 'update',
            'User-Agent' => 'Digiflazz-Hookshot',
        ]);

        $res->assertOk()->assertJson(['ok' => true]);
        $this->assertEquals('success', $trx->fresh()->status);
    }

    public function test_webhook_duplicate_aman(): void
    {
        $trx = $this->makeTrx();
        [$body, $sig] = $this->signed(['ref_id' => 'AP-1', 'status' => 'Sukses', 'rc' => '00']);

        $headers = ['X-Hub-Signature' => $sig, 'X-Digiflazz-Event' => 'update'];
        $this->postJson('/webhook/supplier/digiflazz', json_decode($body, true), $headers)->assertOk();
        $this->postJson('/webhook/supplier/digiflazz', json_decode($body, true), $headers)->assertOk();

        $this->assertEquals('success', $trx->fresh()->status);
    }

    public function test_webhook_gagal_memicu_refund_saldo(): void
    {
        $trx = $this->makeTrx();
        $user = $trx->user;
        [$body, $sig] = $this->signed(['ref_id' => 'AP-1', 'status' => 'Gagal', 'rc' => '02']);

        $this->postJson('/webhook/supplier/digiflazz', json_decode($body, true), [
            'X-Hub-Signature' => $sig,
        ])->assertOk();

        $this->assertEquals('failed', $trx->fresh()->status);
        $this->assertEquals(11500, $user->fresh()->balance);
        $this->assertEquals(1, BalanceMutation::where('transaction_id', $trx->id)->count());
    }

    public function test_signature_salah_ditolak(): void
    {
        $this->makeTrx();
        [$body] = $this->signed(['ref_id' => 'AP-1', 'status' => 'Sukses']);

        $this->postJson('/webhook/supplier/digiflazz', json_decode($body, true), [
            'X-Hub-Signature' => 'sha1=salah',
        ])->assertStatus(401);
    }
}
