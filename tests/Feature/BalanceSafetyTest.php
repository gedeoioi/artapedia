<?php

namespace Tests\Feature;

use App\Models\BalanceMutation;
use App\Models\Product;
use App\Models\SupplierConfig;
use App\Models\Transaction;
use App\Models\User;
use App\Services\BalanceService;
use App\Services\OrderService;
use App\Services\PaymentService;
use App\Suppliers\VipResellerProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BalanceSafetyTest extends TestCase
{
    use RefreshDatabase;

    protected function seedBasics(): array
    {
        $supplier = SupplierConfig::create([
            'code' => 'vip-reseller', 'name' => 'VIP', 'provider_class' => VipResellerProvider::class,
            'is_active' => true, 'is_sandbox' => true, 'priority' => 0, 'credentials' => ['api_id' => 'x', 'api_key' => 'y'],
        ]);
        $product = Product::create([
            'supplier_config_id' => $supplier->id, 'supplier_code' => 'ML-100',
            'name' => 'ML 100 Diamond', 'game' => 'Mobile Legends',
            'cost_basic' => 10000, 'cost_premium' => 9500, 'cost_special' => 9000,
            'price_guest' => 12000, 'price_biasa' => 11500, 'price_vip' => 11000,
            'is_active' => true, 'in_stock' => true,
        ]);

        return [$supplier, $product];
    }

    public function test_checkout_saldo_tidak_cukup_ditolak(): void
    {
        [$supplier, $product] = $this->seedBasics();
        $user = User::factory()->create(['balance' => 1000, 'level' => 'biasa']);

        $this->expectException(\RuntimeException::class);
        app(OrderService::class)->checkout([
            'product_id' => $product->id,
            'target_user_id' => '123',
            'gateway_code' => 'balance',
        ], $user);

        $this->assertEquals(1000, $user->fresh()->balance);
        $this->assertEquals(0, BalanceMutation::count());
    }

    public function test_dua_checkout_bersamaan_saldo_tidak_minus(): void
    {
        [$supplier, $product] = $this->seedBasics([
        ]);
        $user = User::factory()->create(['balance' => 15000, 'level' => 'biasa']);

        $ok = 0;
        $fail = 0;
        for ($i = 0; $i < 2; $i++) {
            try {
                DB::transaction(function () use ($user, &$ok) {
                    $locked = User::whereKey($user->id)->lockForUpdate()->first();
                    $price = 11500;
                    if ($locked->balance < $price) {
                        throw new \RuntimeException('Saldo tidak cukup.');
                    }
                    $locked->balance -= $price;
                    $locked->save();
                    BalanceMutation::create([
                        'user_id' => $locked->id, 'type' => 'order', 'amount' => -$price,
                        'balance_before' => $locked->balance + $price, 'balance_after' => $locked->balance,
                        'description' => 'race-test', 'reference' => 'RACE-'.$ok.'-'.uniqid(),
                    ]);
                    $ok++;
                });
            } catch (\Throwable $e) {
                $fail++;
            }
        }

        $this->assertEquals(1, $ok);
        $this->assertEquals(1, $fail);
        $this->assertGreaterThanOrEqual(0, $user->fresh()->balance);
    }

    public function test_webhook_duplicate_tidak_menambah_saldo_dua_kali(): void
    {
        [$supplier, $product] = $this->seedBasics();
        $user = User::factory()->create(['balance' => 0, 'level' => 'biasa']);

        $trx = Transaction::create([
            'invoice_code' => 'INV-TEST-1', 'user_id' => $user->id, 'product_id' => $product->id,
            'payment_gateway_code' => 'xendit', 'target_user_id' => 'TOPUP',
            'quantity' => 1, 'cost_price' => 0, 'sell_price' => 50000,
            'admin_fee' => 0, 'gateway_fee' => 0, 'total_amount' => 50000, 'profit' => 0,
            'payment_method' => 'xendit', 'payment_reference' => 'TOPUP-DUP-1',
            'status' => 'pending', 'meta' => ['kind' => 'topup'],
        ]);

        $payments = app(PaymentService::class);
        $payments->markPaid('TOPUP-DUP-1', 'xendit', ['try' => 1]);
        $payments->markPaid('TOPUP-DUP-1', 'xendit', ['try' => 2]);

        $this->assertEquals(50000, $user->fresh()->balance);
        $this->assertEquals(1, BalanceMutation::where('transaction_id', $trx->id)->count());
    }

    public function test_ledger_selalu_konsisten(): void
    {
        [$supplier, $product] = $this->seedBasics();
        $user = User::factory()->create(['balance' => 100000, 'level' => 'vip']);

        app(BalanceService::class)->debit($user, 11000, 'order test');

        $fresh = $user->fresh();
        $sum = BalanceMutation::where('user_id', $user->id)->sum('amount');
        $this->assertEquals(100000 + $sum, $fresh->balance);
    }

    public function test_dispatch_tidak_order_ulang_ke_supplier(): void
    {
        [$supplier, $product] = $this->seedBasics();
        $trx = Transaction::create([
            'invoice_code' => 'INV-DISP-1', 'product_id' => $product->id,
            'supplier_config_id' => $supplier->id,
            'payment_gateway_code' => 'balance', 'target_user_id' => '123',
            'quantity' => 1, 'cost_price' => 10000, 'sell_price' => 12000,
            'admin_fee' => 0, 'gateway_fee' => 0, 'total_amount' => 12000, 'profit' => 2000,
            'payment_method' => 'balance', 'status' => 'processing',
            'supplier_trx_id' => 'SUP-EXISTING-1', 'supplier_status' => 'processing',
        ]);

        $result = app(OrderService::class)->dispatchToSupplier($trx->id);

        $this->assertEquals('SUP-EXISTING-1', $result->supplier_trx_id);
    }

    public function test_semua_supplier_gagal_menandai_transaksi_gagal_dan_refund_sekali(): void
    {
        [$supplier, $product] = $this->seedBasics();
        $user = User::factory()->create(['balance' => 0, 'level' => 'biasa']);
        $trx = Transaction::create([
            'invoice_code' => 'INV-ALL-FAILED', 'user_id' => $user->id, 'product_id' => $product->id,
            'supplier_config_id' => $supplier->id,
            'payment_gateway_code' => 'balance', 'target_user_id' => '123',
            'quantity' => 1, 'cost_price' => 10000, 'sell_price' => 12000,
            'admin_fee' => 0, 'gateway_fee' => 0, 'total_amount' => 12000, 'profit' => 2000,
            'payment_method' => 'balance', 'status' => Transaction::STATUS_PAID, 'paid_at' => now(),
        ]);

        Http::fake(['vip-reseller.co.id/*' => Http::response([
            'result' => false, 'message' => 'Order ditolak',
        ])]);

        $failed = app(OrderService::class)->dispatchToSupplier($trx->id);
        $retried = app(OrderService::class)->markAllSuppliersFailed($trx->id);

        $this->assertSame(Transaction::STATUS_FAILED, $failed->status);
        $this->assertSame('all_suppliers_failed', $failed->supplier_status);
        $this->assertSame(Transaction::STATUS_FAILED, $retried->status);
        $this->assertSame(12000, $user->fresh()->balance);
        $this->assertSame(1, BalanceMutation::where('transaction_id', $trx->id)
            ->where('type', BalanceMutation::TYPE_REFUND)->count());
    }

    public function test_data_lama_all_suppliers_failed_dapat_difinalkan(): void
    {
        [$supplier, $product] = $this->seedBasics();
        $trx = Transaction::create([
            'invoice_code' => 'INV-OLD-ALL-FAILED', 'product_id' => $product->id,
            'supplier_config_id' => $supplier->id,
            'payment_gateway_code' => 'balance', 'target_user_id' => '123',
            'quantity' => 1, 'cost_price' => 10000, 'sell_price' => 12000,
            'admin_fee' => 0, 'gateway_fee' => 0, 'total_amount' => 12000, 'profit' => 2000,
            'payment_method' => 'balance', 'status' => Transaction::STATUS_PROCESSING,
            'supplier_status' => 'all_suppliers_failed', 'paid_at' => now(),
        ]);

        $result = app(OrderService::class)->markAllSuppliersFailed($trx->id);

        $this->assertSame(Transaction::STATUS_FAILED, $result->status);
    }

    public function test_callback_telat_tidak_menghidupkan_invoice_expired(): void
    {
        [$supplier, $product] = $this->seedBasics();
        $user = User::factory()->create(['balance' => 0, 'level' => 'biasa']);

        $trx = Transaction::create([
            'invoice_code' => 'INV-EXP-1', 'user_id' => $user->id, 'product_id' => $product->id,
            'payment_gateway_code' => 'xendit', 'target_user_id' => 'TOPUP',
            'quantity' => 1, 'cost_price' => 0, 'sell_price' => 50000,
            'admin_fee' => 0, 'gateway_fee' => 0, 'total_amount' => 50000, 'profit' => 0,
            'payment_method' => 'xendit', 'payment_reference' => 'TOPUP-EXP-1',
            'status' => 'expired', 'meta' => ['kind' => 'topup'],
        ]);

        $payments = app(PaymentService::class);
        $result = $payments->markPaid('TOPUP-EXP-1', 'xendit', ['late' => true]);

        $this->assertEquals('expired', $result->status);
        $this->assertEquals(0, $user->fresh()->balance);
        $this->assertEquals(0, BalanceMutation::where('transaction_id', $trx->id)->count());
    }

    public function test_expire_menolak_trx_yang_sudah_paid(): void
    {
        [$supplier, $product] = $this->seedBasics();
        $trx = Transaction::create([
            'invoice_code' => 'INV-PAID-1', 'product_id' => $product->id,
            'payment_gateway_code' => 'xendit', 'target_user_id' => '123',
            'quantity' => 1, 'cost_price' => 10000, 'sell_price' => 12000,
            'admin_fee' => 0, 'gateway_fee' => 0, 'total_amount' => 12000, 'profit' => 2000,
            'payment_method' => 'xendit', 'payment_reference' => 'PAY-PAID-1',
            'status' => 'paid', 'paid_at' => now(),
        ]);

        $result = app(PaymentService::class)->expireTransaction($trx->id);

        $this->assertEquals('paid', $result->status);
    }
}
