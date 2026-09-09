<?php

namespace Tests\Feature;

use App\Jobs\DispatchOrderToSupplier;
use App\Models\Product;
use App\Models\SupplierConfig;
use App\Models\User;
use App\Services\OrderService;
use App\Suppliers\VipResellerProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class CheckoutQuantityTest extends TestCase
{
    use RefreshDatabase;

    public function test_checkout_vip_menampilkan_pilihan_jumlah_hingga_sepuluh(): void
    {
        [, $product] = $this->vipProduct();

        $this->get(route('checkout.show', $product))
            ->assertOk()
            ->assertSee('Jumlah Pesanan')
            ->assertSee('name="quantity"', false)
            ->assertSee('max="10"', false);
    }

    public function test_jumlah_mengubah_total_dan_dikirim_ke_vip_reseller(): void
    {
        Queue::fake([DispatchOrderToSupplier::class]);
        Http::fake([
            'vip-reseller.co.id/api/game-feature' => Http::response([
                'result' => true,
                'data' => [
                    'trxid' => 'VIP-QTY-3',
                    'status' => 'waiting',
                    'price' => 30000,
                    'balance' => 500000,
                ],
                'message' => 'Pesanan diterima.',
            ]),
        ]);
        [, $product] = $this->vipProduct();
        $user = User::factory()->create(['balance' => 100000, 'level' => 'member']);

        $transaction = app(OrderService::class)->checkout([
            'product_id' => $product->id,
            'target_user_id' => '12345678',
            'target_zone' => '1234',
            'quantity' => 3,
            'gateway_code' => 'balance',
        ], $user);

        $this->assertSame(3, $transaction->quantity);
        $this->assertSame(36000, $transaction->sell_price);
        $this->assertSame(36000, $transaction->total_amount);
        $this->assertSame(64000, $user->fresh()->balance);

        app(OrderService::class)->dispatchToSupplier($transaction->id);

        Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/api/game-feature')
            && (int) $request['quantity'] === 3);
    }

    private function vipProduct(): array
    {
        $supplier = SupplierConfig::create([
            'code' => 'vip-reseller',
            'name' => 'VIP Reseller',
            'provider_class' => VipResellerProvider::class,
            'is_active' => true,
            'is_sandbox' => true,
            'priority' => 0,
            'credentials' => ['api_id' => 'test-id', 'api_key' => 'test-key'],
        ]);
        $product = Product::create([
            'supplier_config_id' => $supplier->id,
            'supplier_code' => 'ML-12',
            'name' => '12 Diamonds',
            'game' => 'Mobile Legends',
            'product_type' => Product::TYPE_GAME,
            'cost_basic' => 10000,
            'cost_premium' => 9500,
            'cost_special' => 9000,
            'price_guest' => 12000,
            'price_biasa' => 11500,
            'price_vip' => 11000,
            'is_active' => true,
            'in_stock' => true,
        ]);

        return [$supplier, $product];
    }
}
