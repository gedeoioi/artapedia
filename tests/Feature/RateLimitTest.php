<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\SupplierConfig;
use App\Models\Transaction;
use App\Models\User;
use App\Suppliers\VipResellerProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class RateLimitTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Cache array per-test, tapi pastikan limiter bersih di antara test.
        RateLimiter::clear('checkout:127.0.0.1');
    }

    protected function seedProduct(): Product
    {
        $supplier = SupplierConfig::create([
            'code' => 'vip-reseller', 'name' => 'VIP', 'provider_class' => VipResellerProvider::class,
            'is_active' => true, 'is_sandbox' => true, 'priority' => 0,
            'credentials' => ['api_id' => 'x', 'api_key' => 'y'],
        ]);

        return Product::create([
            'supplier_config_id' => $supplier->id, 'supplier_code' => 'ML-100',
            'name' => 'ML 100 Diamond', 'game' => 'Mobile Legends',
            'cost_basic' => 10000, 'cost_premium' => 9500, 'cost_special' => 9000,
            'price_guest' => 12000, 'price_biasa' => 11500, 'price_vip' => 11000,
            'is_active' => true, 'in_stock' => true,
        ]);
    }

    public function test_cek_invoice_dibatasi_untuk_mencegah_enumerasi(): void
    {
        $user = User::factory()->create();
        Transaction::create([
            'invoice_code' => 'INV-RATE-1', 'user_id' => $user->id,
            'payment_gateway_code' => 'xendit', 'target_user_id' => '123',
            'quantity' => 1, 'cost_price' => 0, 'sell_price' => 10000,
            'admin_fee' => 0, 'gateway_fee' => 0, 'total_amount' => 10000, 'profit' => 0,
            'payment_method' => 'xendit', 'status' => Transaction::STATUS_PENDING,
        ]);

        // Limit per nilai yang dicari adalah 5/menit.
        for ($i = 0; $i < 5; $i++) {
            $this->get('/cek-invoice/hasil?code=INV-RATE-1')->assertOk();
        }

        $this->get('/cek-invoice/hasil?code=INV-RATE-1')->assertStatus(429);
    }

    public function test_enumerasi_kode_berbeda_dibatasi_per_ip(): void
    {
        // Menebak-nebak nomor invoice berbeda tetap kena batas per IP (15/menit).
        for ($i = 0; $i < 15; $i++) {
            $this->get('/cek-invoice/hasil?code=INV-NOPE-'.$i)->assertOk();
        }

        $this->get('/cek-invoice/hasil?code=INV-NOPE-FINAL')->assertStatus(429);
    }

    public function test_api_invoice_ikut_dibatasi(): void
    {
        $user = User::factory()->create();
        Transaction::create([
            'invoice_code' => 'INV-API-1', 'user_id' => $user->id,
            'payment_gateway_code' => 'xendit', 'target_user_id' => '123',
            'quantity' => 1, 'cost_price' => 0, 'sell_price' => 10000,
            'admin_fee' => 0, 'gateway_fee' => 0, 'total_amount' => 10000, 'profit' => 0,
            'payment_method' => 'xendit', 'status' => Transaction::STATUS_PENDING,
        ]);

        for ($i = 0; $i < 5; $i++) {
            $this->getJson('/api/invoice/INV-API-1')->assertOk();
        }

        $this->getJson('/api/invoice/INV-API-1')->assertStatus(429);
    }

    public function test_checkout_dibatasi_per_menit(): void
    {
        $product = $this->seedProduct();

        // Limit checkout 10/menit. Request dengan payload tidak valid pun
        // terhitung, jadi 10 percobaan lalu yang ke-11 harus 429.
        for ($i = 0; $i < 10; $i++) {
            $this->postJson('/checkout/quote', [
                'product_id' => $product->id,
                'gateway_code' => 'balance',
            ]);
        }

        $this->postJson('/checkout/quote', [
            'product_id' => $product->id,
            'gateway_code' => 'balance',
        ])->assertStatus(429);
    }

    public function test_cek_nickname_dibatasi_lebih_ketat(): void
    {
        $product = $this->seedProduct();

        for ($i = 0; $i < 20; $i++) {
            $this->postJson('/checkout/check-nickname', [
                'product_id' => $product->id,
                'target_user_id' => '123',
            ]);
        }

        $this->postJson('/checkout/check-nickname', [
            'product_id' => $product->id,
            'target_user_id' => '123',
        ])->assertStatus(429);
    }

    public function test_webhook_dibatasi_per_ip(): void
    {
        // Limit webhook 120/menit.
        for ($i = 0; $i < 120; $i++) {
            $this->postJson('/webhook/payment/xendit', []);
        }

        $this->postJson('/webhook/payment/xendit', [])->assertStatus(429);
    }

    public function test_topup_membutuhkan_login_dan_dibatasi(): void
    {
        // Route topup ada di grup auth, jadi guest dialihkan ke login.
        $this->post('/member/topup', [])->assertRedirect('/login');
    }
}
