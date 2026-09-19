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

class OrderIdempotencyTest extends TestCase
{
    use RefreshDatabase;

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

    private function fakeSupplier(): void
    {
        Http::fake(['vip-reseller.co.id/*' => Http::response([
            'result' => true,
            'data' => ['trxid' => 'VIP-TRX-1', 'status' => 'waiting', 'price' => 10000, 'balance' => 500000],
            'message' => 'Pesanan diterima.',
        ])]);
    }

    /**
     * Skenario double-click: supplier menerima order dan transaksi masih hidup
     * (processing). Request berikutnya yang identik harus mengembalikan
     * transaksi yang sama, bukan membuat order kedua.
     */
    public function test_checkout_identik_berulang_tidak_membuat_transaksi_kedua(): void
    {
        $this->fakeSupplier();
        $product = $this->seedProduct();
        $user = User::factory()->create(['balance' => 100000, 'level' => 'biasa']);

        $payload = [
            'product_id' => $product->id,
            'target_user_id' => '123456789',
            'gateway_code' => 'balance',
        ];

        $first = app(OrderService::class)->checkout($payload, $user);
        $second = app(OrderService::class)->checkout($payload, $user);
        $third = app(OrderService::class)->checkout($payload, $user);

        $this->assertSame($first->id, $second->id);
        $this->assertSame($first->id, $third->id);
        $this->assertSame(1, Transaction::count());
        $this->assertSame(Transaction::STATUS_PROCESSING, $first->fresh()->status);
    }

    /**
     * Inti masalahnya: saldo hanya boleh terpotong sekali walaupun checkout
     * dipanggil berkali-kali. Double-spend tidak bisa dikembalikan kalau kredit
     * suppliernya sudah terkirim.
     */
    public function test_saldo_hanya_terpotong_sekali_untuk_checkout_berulang(): void
    {
        $this->fakeSupplier();
        $product = $this->seedProduct();
        $user = User::factory()->create(['balance' => 100000, 'level' => 'biasa']);

        $payload = [
            'product_id' => $product->id,
            'target_user_id' => '123456789',
            'gateway_code' => 'balance',
        ];

        for ($i = 0; $i < 3; $i++) {
            app(OrderService::class)->checkout($payload, $user);
        }

        // Harga level biasa = 11.500, jadi saldo harus 88.500 — bukan 65.500.
        $this->assertSame(88500, $user->fresh()->balance);
    }

    public function test_supplier_hanya_dipanggil_sekali_untuk_checkout_berulang(): void
    {
        $this->fakeSupplier();
        $product = $this->seedProduct();
        $user = User::factory()->create(['balance' => 100000, 'level' => 'biasa']);

        $payload = [
            'product_id' => $product->id,
            'target_user_id' => '123456789',
            'gateway_code' => 'balance',
        ];

        for ($i = 0; $i < 3; $i++) {
            app(OrderService::class)->checkout($payload, $user);
        }

        Http::assertSentCount(1);
    }

    public function test_tujuan_berbeda_tetap_membuat_transaksi_terpisah(): void
    {
        $this->fakeSupplier();
        $product = $this->seedProduct();
        $user = User::factory()->create(['balance' => 100000, 'level' => 'biasa']);

        app(OrderService::class)->checkout([
            'product_id' => $product->id, 'target_user_id' => '111', 'gateway_code' => 'balance',
        ], $user);

        app(OrderService::class)->checkout([
            'product_id' => $product->id, 'target_user_id' => '222', 'gateway_code' => 'balance',
        ], $user);

        $this->assertSame(2, Transaction::count());
    }

    /**
     * Order gagal tidak boleh mengunci pembeli. Kalau kuncinya tidak dilepas,
     * unique index akan melempar error constraint saat pembeli mencoba ulang
     * pada jendela waktu yang sama.
     */
    public function test_order_gagal_melepas_kunci_sehingga_pembeli_bisa_coba_ulang(): void
    {
        // Panggilan pertama ditolak supplier, panggilan berikutnya diterima.
        // Http::fake() MENAMBAH stub, tidak menggantinya — jadi percobaan kedua
        // harus memakai satu closure berurutan, bukan fake() kedua.
        $calls = 0;
        Http::fake(['vip-reseller.co.id/*' => function () use (&$calls) {
            $calls++;

            if ($calls === 1) {
                return Http::response(['result' => false, 'message' => 'Stok kosong']);
            }

            return Http::response([
                'result' => true,
                'data' => ['trxid' => 'VIP-TRX-1', 'status' => 'waiting', 'price' => 10000, 'balance' => 500000],
                'message' => 'Pesanan diterima.',
            ]);
        }]);

        $product = $this->seedProduct();
        $user = User::factory()->create(['balance' => 100000, 'level' => 'biasa']);

        $payload = [
            'product_id' => $product->id,
            'target_user_id' => '123456789',
            'gateway_code' => 'balance',
        ];

        $first = app(OrderService::class)->checkout($payload, $user);
        $this->assertSame(Transaction::STATUS_FAILED, $first->fresh()->status);
        $this->assertNull($first->fresh()->idempotency_key, 'Order gagal harus melepas kuncinya.');

        $second = app(OrderService::class)->checkout($payload, $user);

        $this->assertNotSame($first->id, $second->id, 'Setelah gagal, pembeli harus bisa order lagi.');
        $this->assertSame(Transaction::STATUS_PROCESSING, $second->fresh()->status);
        $this->assertSame(2, Transaction::count());
    }

    public function test_kolom_idempotency_key_punya_unique_index(): void
    {
        $this->fakeSupplier();
        $product = $this->seedProduct();
        $user = User::factory()->create(['balance' => 100000, 'level' => 'biasa']);

        $trx = app(OrderService::class)->checkout([
            'product_id' => $product->id, 'target_user_id' => '999', 'gateway_code' => 'balance',
        ], $user);

        $this->assertNotNull($trx->idempotency_key);

        // Penjaga terakhir di level DB: baris kedua dengan key sama harus ditolak.
        $this->expectException(\Illuminate\Database\QueryException::class);

        Transaction::create([
            'invoice_code' => 'INV-DUPE-KEY', 'user_id' => $user->id, 'product_id' => $product->id,
            'payment_gateway_code' => 'balance', 'target_user_id' => '999',
            'quantity' => 1, 'cost_price' => 0, 'sell_price' => 0,
            'admin_fee' => 0, 'gateway_fee' => 0, 'total_amount' => 0, 'profit' => 0,
            'payment_method' => 'balance', 'status' => Transaction::STATUS_PENDING,
            'idempotency_key' => $trx->idempotency_key,
        ]);
    }

    public function test_key_berbeda_untuk_metode_bayar_berbeda(): void
    {
        $product = $this->seedProduct();

        $a = Transaction::idempotencyKey([
            'product_id' => $product->id, 'target_user_id' => '123', 'gateway_code' => 'balance',
        ]);
        $b = Transaction::idempotencyKey([
            'product_id' => $product->id, 'target_user_id' => '123', 'gateway_code' => 'xendit',
        ]);

        $this->assertNotSame($a, $b);
    }
}
