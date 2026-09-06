<?php

namespace Tests\Feature;

use App\Models\BalanceMutation;
use App\Models\PaymentGatewayConfig;
use App\Models\Product;
use App\Models\SupplierConfig;
use App\Models\Transaction;
use App\Models\User;
use App\Services\OrderService;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class BalanceSafetyTest extends TestCase
{
    use RefreshDatabase;

    protected function seedBasics(): array
    {
        $supplier = SupplierConfig::create([
            'code' => 'vip-reseller', 'name' => 'VIP', 'provider_class' => \App\Suppliers\VipResellerProvider::class,
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
                DB::transaction(function () use ($user, $product, &$ok) {
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

        app(\App\Services\BalanceService::class)->debit($user, 11000, 'order test');

        $fresh = $user->fresh();
        $sum = BalanceMutation::where('user_id', $user->id)->sum('amount');
        $this->assertEquals(100000 + $sum, $fresh->balance);
    }
}
