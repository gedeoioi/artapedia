<?php

namespace Tests\Feature;

use App\Models\PaymentGatewayConfig;
use App\Models\Product;
use App\Models\SupplierConfig;
use App\Models\Transaction;
use App\Models\User;
use App\Payments\IPaymuGateway;
use App\Payments\XenditGateway;
use App\Services\OrderService;
use App\Services\PaymentService;
use App\Suppliers\VipResellerProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PaymentSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_xendit_callback_ditolak_tanpa_token_terkonfigurasi(): void
    {
        $gateway = new XenditGateway([], true);

        $result = $gateway->handleCallback(
            ['external_id' => 'PAY-1', 'status' => 'PAID', 'amount' => 10000],
            ['x-callback-token' => ['attacker-token']],
        );

        $this->assertFalse($result['ok']);
        $this->assertSame('invalid_signature', $result['status']);
    }

    public function test_xendit_callback_menerima_header_laravel_berbentuk_array(): void
    {
        $gateway = new XenditGateway(['callback_token' => 'valid-token'], true);

        $result = $gateway->handleCallback(
            ['external_id' => 'PAY-1', 'status' => 'PAID', 'amount' => 10000],
            ['x-callback-token' => ['valid-token']],
        );

        $this->assertTrue($result['ok']);
        $this->assertSame(10000, $result['amount']);
    }

    public function test_ipaymu_callback_memakai_signature_payload_dan_header_x_signature(): void
    {
        $payload = [
            'reference_id' => 'PAY-2',
            'status' => 'berhasil',
            'trx_id' => '123',
            'status_code' => '1',
            'transaction_status_code' => '1',
            'paid_off' => '98500',
            'is_escrow' => '0',
            'amount' => '100000',
        ];
        $normalized = [
            'additional_info' => [],
            'amount' => '100000',
            'is_escrow' => false,
            'paid_off' => 98500,
            'reference_id' => 'PAY-2',
            'status' => 'berhasil',
            'status_code' => 1,
            'transaction_status_code' => 1,
            'trx_id' => 123,
        ];
        $signature = hash_hmac('sha256', (string) json_encode($normalized), '123456');
        $gateway = new IPaymuGateway(['va' => '123456', 'secret' => 'api-secret'], true);

        $result = $gateway->handleCallback($payload, ['x-signature' => [$signature]]);

        $this->assertTrue($result['ok']);
        $this->assertSame('PAY-2', $result['reference_id']);
        $this->assertSame(100000, $result['amount']);
    }

    public function test_callback_gateway_atau_nominal_yang_tidak_sesuai_ditolak(): void
    {
        $trx = $this->pendingTransaction();
        $payments = app(PaymentService::class);

        try {
            $payments->markPaid($trx->payment_reference, 'duitku', [], $trx->total_amount);
            $this->fail('Gateway mismatch seharusnya ditolak.');
        } catch (\UnexpectedValueException) {
            $this->assertSame(Transaction::STATUS_PENDING, $trx->fresh()->status);
        }

        $this->expectException(\UnexpectedValueException::class);
        $payments->markPaid($trx->payment_reference, 'xendit', [], $trx->total_amount - 1);
    }

    public function test_transaksi_belum_dibayar_tidak_dikirim_ke_supplier(): void
    {
        $trx = $this->pendingTransaction();
        Http::fake();

        $result = app(OrderService::class)->dispatchToSupplier($trx->id);

        $this->assertSame(Transaction::STATUS_PENDING, $result->status);
        $this->assertNull($result->supplier_trx_id);
        Http::assertNothingSent();
    }

    public function test_callback_invoice_lama_tetap_diproses_setelah_gateway_dinonaktifkan(): void
    {
        $trx = $this->pendingTransaction();
        $trx->update(['meta' => ['kind' => 'topup'], 'sell_price' => 12000]);
        PaymentGatewayConfig::create([
            'code' => 'xendit',
            'name' => 'Xendit',
            'gateway_class' => XenditGateway::class,
            'is_active' => false,
            'is_sandbox' => true,
            'sort_order' => 0,
            'credentials' => ['callback_token' => 'valid-token'],
            'fee_flat' => 0,
            'fee_percent' => 0,
        ]);

        $response = $this->postJson('/webhook/payment/xendit', [
            'external_id' => $trx->payment_reference,
            'status' => 'PAID',
            'amount' => $trx->total_amount,
        ], ['X-Callback-Token' => 'valid-token']);

        $response->assertOk()->assertJson(['ok' => true]);
        $this->assertSame(Transaction::STATUS_SUCCESS, $trx->fresh()->status);
        $this->assertSame(12000, $trx->user->fresh()->balance);
    }

    private function pendingTransaction(): Transaction
    {
        $supplier = SupplierConfig::create([
            'code' => 'vip-reseller',
            'name' => 'VIP',
            'provider_class' => VipResellerProvider::class,
            'is_active' => true,
            'is_sandbox' => true,
            'priority' => 0,
            'credentials' => ['api_id' => 'id', 'api_key' => 'key'],
        ]);
        $product = Product::create([
            'supplier_config_id' => $supplier->id,
            'supplier_code' => 'ML-100',
            'name' => 'ML 100',
            'game' => 'Mobile Legends',
            'cost_basic' => 10000,
            'cost_premium' => 9500,
            'cost_special' => 9000,
            'price_guest' => 12000,
            'price_biasa' => 11500,
            'price_vip' => 11000,
            'is_active' => true,
            'in_stock' => true,
        ]);
        $user = User::factory()->create();

        return Transaction::create([
            'invoice_code' => 'INV-SECURITY-1',
            'user_id' => $user->id,
            'product_id' => $product->id,
            'supplier_config_id' => $supplier->id,
            'payment_gateway_code' => 'xendit',
            'target_user_id' => '12345',
            'quantity' => 1,
            'cost_price' => 10000,
            'sell_price' => 12000,
            'admin_fee' => 0,
            'gateway_fee' => 0,
            'total_amount' => 12000,
            'profit' => 2000,
            'payment_method' => 'xendit',
            'payment_reference' => 'PAY-SECURITY-1',
            'status' => Transaction::STATUS_PENDING,
        ]);
    }
}
