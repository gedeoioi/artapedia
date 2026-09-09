<?php

namespace Tests\Feature;

use App\Jobs\DispatchOrderToSupplier;
use App\Models\Product;
use App\Models\SupplierConfig;
use App\Models\Transaction;
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

    public function test_jumlah_dua_membuat_dua_order_supplier_dan_menggabungkan_statusnya(): void
    {
        Queue::fake([DispatchOrderToSupplier::class]);
        Http::fake([
            'vip-reseller.co.id/api/game-feature' => Http::sequence()
                ->push($this->vipOrderResponse('VIP-QTY-1'))
                ->push($this->vipStatusResponse('VIP-QTY-1', 'success'))
                ->push($this->vipOrderResponse('VIP-QTY-2'))
                ->push($this->vipStatusResponse('VIP-QTY-2', 'success')),
        ]);
        [, $product] = $this->vipProduct();
        $user = User::factory()->create(['balance' => 100000, 'level' => 'member']);

        $transaction = app(OrderService::class)->checkout([
            'product_id' => $product->id,
            'target_user_id' => '12345678',
            'target_zone' => '1234',
            'quantity' => 2,
            'gateway_code' => 'balance',
        ], $user);

        $this->assertSame(2, $transaction->quantity);
        $this->assertSame(24000, $transaction->sell_price);
        $this->assertSame(24000, $transaction->total_amount);
        $this->assertSame(76000, $user->fresh()->balance);

        $processing = app(OrderService::class)->dispatchToSupplier($transaction->id);
        $this->assertSame('VIP-QTY-1', $processing->supplier_trx_id);
        $this->assertSame(
            ['VIP-QTY-1'],
            collect($processing->payment_payload['supplier_orders'])->pluck('trxid')->all(),
        );

        $secondQueued = app(OrderService::class)->pollStatus($transaction->id);
        $this->assertSame('processing', $secondQueued->status);
        $this->assertSame(
            ['VIP-QTY-1', 'VIP-QTY-2'],
            collect($secondQueued->payment_payload['supplier_orders'])->pluck('trxid')->all(),
        );

        $finished = app(OrderService::class)->pollStatus($transaction->id);
        $this->assertSame('success', $finished->status);

        $orderRequests = collect(Http::recorded())
            ->map(fn (array $record) => $record[0])
            ->filter(fn (Request $request) => $request['type'] === 'order');
        $this->assertCount(2, $orderRequests);
        $this->assertTrue($orderRequests->every(fn (Request $request) => ! isset($request['quantity'])));
    }

    public function test_webhook_id_order_batch_kedua_memperbarui_invoice_yang_sama(): void
    {
        Queue::fake([DispatchOrderToSupplier::class]);
        Http::fake([
            'vip-reseller.co.id/api/game-feature' => Http::sequence()
                ->push($this->vipOrderResponse('VIP-WEBHOOK-1'))
                ->push($this->vipOrderResponse('VIP-WEBHOOK-2')),
        ]);
        [, $product] = $this->vipProduct();
        $user = User::factory()->create(['balance' => 100000, 'level' => 'member']);
        $transaction = app(OrderService::class)->checkout([
            'product_id' => $product->id,
            'target_user_id' => '12345678',
            'target_zone' => '1234',
            'quantity' => 2,
            'gateway_code' => 'balance',
        ], $user);
        app(OrderService::class)->dispatchToSupplier($transaction->id);
        $signature = md5('test-id'.'test-key');

        $this->postJson(route('webhook.supplier.vip-reseller'), [
            'data' => ['trxid' => 'VIP-WEBHOOK-1', 'status' => 'success', 'note' => 'Success 1'],
        ], ['X-Client-Signature' => $signature])
            ->assertOk()
            ->assertJson(['matched' => true, 'transaction_status' => 'processing']);

        $this->postJson(route('webhook.supplier.vip-reseller'), [
            'data' => ['trxid' => 'VIP-WEBHOOK-2', 'status' => 'success', 'note' => 'Success 2'],
        ], ['X-Client-Signature' => $signature])
            ->assertOk()
            ->assertJson(['matched' => true, 'transaction_status' => 'success']);

        $this->assertSame('success', $transaction->fresh()->status);
    }

    public function test_batch_lama_dengan_satu_supplier_id_dilanjutkan_tanpa_mengulang_order_pertama(): void
    {
        Queue::fake([DispatchOrderToSupplier::class]);
        Http::fake([
            'vip-reseller.co.id/api/game-feature' => Http::sequence()
                ->push($this->vipStatusResponse('VIP-LEGACY-1', 'success'))
                ->push($this->vipOrderResponse('VIP-LEGACY-2')),
        ]);
        [$supplier, $product] = $this->vipProduct();
        $user = User::factory()->create(['balance' => 100000, 'level' => 'member']);
        $transaction = app(OrderService::class)->checkout([
            'product_id' => $product->id,
            'target_user_id' => '12345678',
            'target_zone' => '1234',
            'quantity' => 2,
            'gateway_code' => 'balance',
        ], $user);
        $transaction->update([
            'supplier_config_id' => $supplier->id,
            'supplier_trx_id' => 'VIP-LEGACY-1',
            'supplier_status' => 'waiting',
            'status' => 'processing',
            'payment_payload' => null,
        ]);

        $recovered = app(OrderService::class)->pollStatus($transaction->id);

        $this->assertSame(
            ['VIP-LEGACY-1', 'VIP-LEGACY-2'],
            collect($recovered->payment_payload['supplier_orders'])->pluck('trxid')->all(),
        );
        $orderRequests = collect(Http::recorded())
            ->map(fn (array $record) => $record[0])
            ->filter(fn (Request $request) => $request['type'] === 'order');
        $this->assertCount(1, $orderRequests);
    }

    public function test_order_batch_berikutnya_yang_ditolak_menyimpan_alasan_supplier(): void
    {
        Queue::fake([DispatchOrderToSupplier::class]);
        Http::fake([
            'vip-reseller.co.id/api/game-feature' => Http::sequence()
                ->push($this->vipOrderResponse('VIP-PARTIAL-1'))
                ->push($this->vipStatusResponse('VIP-PARTIAL-1', 'success'))
                ->push([
                    'result' => false,
                    'message' => 'Transaksi serupa masih dalam proses.',
                ], 200),
        ]);
        [, $product] = $this->vipProduct();
        $user = User::factory()->create(['balance' => 100000, 'level' => 'member']);
        $transaction = app(OrderService::class)->checkout([
            'product_id' => $product->id,
            'target_user_id' => '12345678',
            'target_zone' => '1234',
            'quantity' => 2,
            'gateway_code' => 'balance',
        ], $user);

        app(OrderService::class)->dispatchToSupplier($transaction->id);
        $failed = app(OrderService::class)->pollStatus($transaction->id);

        $this->assertSame('failed', $failed->status);
        $this->assertSame('partial_failed', $failed->supplier_status);
        $this->assertStringContainsString('Transaksi serupa masih dalam proses.', $failed->notes);
        $this->assertSame(88000, $user->fresh()->balance);
    }

    public function test_admin_melihat_supplier_trx_id_terpisah_untuk_setiap_pembelian(): void
    {
        [$supplier, $product] = $this->vipProduct();
        $admin = User::factory()->create(['level' => 'admin']);
        $transaction = Transaction::create([
            'invoice_code' => 'INV-QTY-ADMIN-1',
            'user_id' => $admin->id,
            'product_id' => $product->id,
            'supplier_config_id' => $supplier->id,
            'target_user_id' => '12345678',
            'quantity' => 2,
            'status' => 'processing',
            'supplier_trx_id' => 'VIP-ADMIN-1',
            'supplier_status' => 'batch_1_of_2',
            'payment_payload' => [
                'supplier_orders' => [
                    ['index' => 1, 'trxid' => 'VIP-ADMIN-1', 'status' => 'success', 'sn' => 'SN-001'],
                    ['index' => 2, 'trxid' => 'VIP-ADMIN-2', 'status' => 'waiting'],
                ],
            ],
        ]);

        $this->actingAs($admin)
            ->get("/admin/transactions/{$transaction->id}/edit")
            ->assertOk()
            ->assertSee('Supplier trx id per pembelian')
            ->assertSee('Pembelian #1')
            ->assertSee('Pembelian #2')
            ->assertSee('VIP-ADMIN-1')
            ->assertSee('VIP-ADMIN-2')
            ->assertSee('SN-001');
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

    private function vipOrderResponse(string $trxid): array
    {
        return [
            'result' => true,
            'data' => [
                'trxid' => $trxid,
                'status' => 'waiting',
                'price' => 10000,
                'balance' => 500000,
            ],
            'message' => 'Pesanan diterima.',
        ];
    }

    private function vipStatusResponse(string $trxid, string $status): array
    {
        return [
            'result' => true,
            'data' => [[
                'trxid' => $trxid,
                'status' => $status,
                'note' => $status === 'success' ? 'Success' : '',
                'price' => 10000,
            ]],
            'message' => 'Detail transaksi berhasil didapatkan.',
        ];
    }
}
