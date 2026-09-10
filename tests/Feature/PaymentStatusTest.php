<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\SupplierConfig;
use App\Models\Transaction;
use App\Models\User;
use App\Suppliers\VipResellerProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PaymentStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_produk_sukses_otomatis_mengarahkan_member_ke_member_area(): void
    {
        $member = User::factory()->create();
        $product = Product::create([
            'supplier_code' => 'ML-SUCCESS', 'name' => '5 Diamonds', 'game' => 'Mobile Legends',
            'cost_basic' => 1000, 'cost_premium' => 1000, 'cost_special' => 1000,
            'price_guest' => 1200, 'price_biasa' => 1150, 'price_vip' => 1100,
            'is_active' => true, 'in_stock' => true,
        ]);
        $trx = Transaction::create([
            'invoice_code' => 'INV-PRODUCT-SUCCESS', 'product_id' => $product->id,
            'user_id' => $member->id, 'payment_gateway_code' => 'balance', 'target_user_id' => '123456',
            'quantity' => 1, 'cost_price' => 1000, 'sell_price' => 1100,
            'admin_fee' => 0, 'gateway_fee' => 0, 'total_amount' => 1100, 'profit' => 100,
            'payment_method' => 'balance', 'status' => Transaction::STATUS_SUCCESS,
            'supplier_trx_id' => 'VP-SUCCESS', 'supplier_status' => 'success', 'paid_at' => now(),
        ]);

        $this->actingAs($member)
            ->get(route('payment.show', $trx->invoice_code))
            ->assertOk()
            ->assertSee('Pesanan berhasil diproses. Mengarahkan ke Member Area...')
            ->assertSee(route('member.dashboard'), false)
            ->assertSee('window.location.replace(memberAreaRedirectUrl)', false);
    }

    public function test_halaman_processing_menampilkan_proses_supplier_bukan_menunggu_pembayaran(): void
    {
        $product = Product::create([
            'supplier_code' => 'ML5', 'name' => '5 Diamonds', 'game' => 'Mobile Legends',
            'cost_basic' => 1000, 'cost_premium' => 1000, 'cost_special' => 1000,
            'price_guest' => 1200, 'price_biasa' => 1150, 'price_vip' => 1100,
            'is_active' => true, 'in_stock' => true,
        ]);
        $trx = Transaction::create([
            'invoice_code' => 'INV-STATUS-1', 'product_id' => $product->id,
            'payment_gateway_code' => 'balance', 'target_user_id' => '123456',
            'quantity' => 1, 'cost_price' => 1000, 'sell_price' => 1200,
            'admin_fee' => 0, 'gateway_fee' => 0, 'total_amount' => 1200, 'profit' => 200,
            'payment_method' => 'balance', 'status' => Transaction::STATUS_PROCESSING,
            'supplier_trx_id' => 'VP123', 'supplier_status' => 'waiting', 'paid_at' => now(),
        ]);

        $this->get(route('payment.show', $trx->invoice_code))
            ->assertOk()
            ->assertSee('Sedang diproses')
            ->assertDontSee('Pesanan sudah diterima supplier dan sedang dalam antrean.')
            ->assertDontSee('Status supplier:')
            ->assertDontSee('Status: processing - menunggu pembayaran terdeteksi');

        $this->getJson(route('payment.status', $trx->invoice_code))
            ->assertOk()
            ->assertJson([
                'status' => 'processing',
                'status_label' => 'Sedang diproses',
                'supplier_status' => 'waiting',
                'badge' => 'badge-pending',
            ]);
    }

    public function test_endpoint_status_otomatis_sinkron_ke_supplier(): void
    {
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
            'invoice_code' => 'INV-STATUS-AUTO', 'product_id' => $product->id,
            'supplier_config_id' => $supplier->id,
            'payment_gateway_code' => 'balance', 'target_user_id' => '123456',
            'quantity' => 1, 'cost_price' => 1000, 'sell_price' => 1200,
            'admin_fee' => 0, 'gateway_fee' => 0, 'total_amount' => 1200, 'profit' => 200,
            'payment_method' => 'balance', 'status' => Transaction::STATUS_PROCESSING,
            'supplier_trx_id' => 'VP-AUTO-1', 'supplier_status' => 'waiting', 'paid_at' => now(),
        ]);

        Http::fake(['vip-reseller.co.id/api/game-feature' => Http::response([
            'result' => true,
            'data' => [['trxid' => 'VP-AUTO-1', 'status' => 'success', 'note' => 'SN123', 'price' => 1000]],
        ])]);

        $this->getJson(route('payment.status', $trx->invoice_code))
            ->assertOk()
            ->assertJson(['status' => 'success', 'supplier_status' => 'success']);

        $this->assertSame(Transaction::STATUS_SUCCESS, $trx->fresh()->status);
    }
}
