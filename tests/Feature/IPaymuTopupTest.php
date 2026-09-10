<?php

namespace Tests\Feature;

use App\Models\PaymentGatewayConfig;
use App\Models\Product;
use App\Models\SupplierConfig;
use App\Models\Transaction;
use App\Models\User;
use App\Payments\IPaymuGateway;
use App\Suppliers\VipResellerProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class IPaymuTopupTest extends TestCase
{
    use RefreshDatabase;

    public function test_ipaymu_redirect_payment_memakai_payload_dan_signature_yang_sesuai(): void
    {
        Http::fake([
            'sandbox.ipaymu.com/api/v2/payment' => Http::response([
                'Status' => 200,
                'Message' => 'Success',
                'Data' => [
                    'SessionID' => 'session-123',
                    'Url' => 'https://sandbox.ipaymu.com/payment/session-123',
                ],
            ]),
        ]);

        $gateway = new IPaymuGateway(['va' => '123456', 'secret' => 'api-secret'], true);
        $result = $gateway->createPayment([
            'reference_id' => 'TOPUP-123',
            'amount' => 50000,
            'description' => 'Topup saldo 50000',
            'customer_name' => 'Member Test',
            'customer_phone' => '081234567890',
            'customer_email' => 'member@example.com',
        ]);

        $this->assertTrue($result['ok']);
        $this->assertSame('session-123', $result['gateway_ref']);
        $this->assertSame('https://sandbox.ipaymu.com/payment/session-123', $result['pay_url']);

        Http::assertSent(function (Request $request): bool {
            $body = $request->body();
            $expectedSignature = hash_hmac(
                'sha256',
                'POST:123456:'.strtolower(hash('sha256', $body)).':api-secret',
                'api-secret',
            );
            $data = json_decode($body, true, flags: JSON_THROW_ON_ERROR);

            return $request->url() === 'https://sandbox.ipaymu.com/api/v2/payment'
                && $request->header('signature')[0] === $expectedSignature
                && ! array_key_exists('account', $data)
                && $data['product'] === ['Topup saldo 50000']
                && $data['qty'] === [1]
                && $data['price'] === [50000]
                && $data['phone'] === '081234567890'
                && $data['buyerPhone'] === '081234567890';
        });
    }

    public function test_topup_memerlukan_nomor_hp_valid_yang_tersimpan_di_akun(): void
    {
        $user = User::factory()->create(['phone' => null]);
        $this->createGateway();

        $this->actingAs($user)
            ->from(route('topup.create'))
            ->post(route('topup.store'), [
                'amount' => 50000,
                'gateway_code' => 'ipaymu',
            ])
            ->assertRedirect(route('topup.create'))
            ->assertSessionHasErrors('topup');

        Http::assertNothingSent();
    }

    public function test_ipaymu_direct_payment_mengirim_method_dan_channel_pilihan(): void
    {
        Http::fake([
            'sandbox.ipaymu.com/api/v2/payment/direct' => Http::response([
                'Status' => 200,
                'Success' => true,
                'Message' => 'Success',
                'Data' => [
                    'TransactionId' => 98765,
                    'ReferenceId' => 'PAY-DIRECT-1',
                    'Via' => 'va',
                    'Channel' => 'bca',
                    'PaymentNo' => '1234567890',
                    'PaymentName' => 'BCA Virtual Account',
                    'Url' => 'https://sandbox.ipaymu.com/payment/98765',
                ],
            ]),
        ]);

        $gateway = new IPaymuGateway(['va' => '123456', 'secret' => 'api-secret'], true);
        $result = $gateway->createPayment([
            'reference_id' => 'PAY-DIRECT-1',
            'amount' => 10000,
            'description' => 'Pembelian Diamond',
            'customer_name' => 'Member Test',
            'customer_phone' => '081234567890',
            'customer_email' => 'member@example.com',
            'payment_method' => 'va',
            'payment_channel' => 'bca',
        ]);

        $this->assertTrue($result['ok']);
        $this->assertSame(98765, $result['gateway_ref']);

        Http::assertSent(function (Request $request): bool {
            return $request->url() === 'https://sandbox.ipaymu.com/api/v2/payment/direct'
                && $request['paymentMethod'] === 'va'
                && $request['paymentChannel'] === 'bca'
                && $request['amount'] === 10000
                && $request['feeDirection'] === 'MERCHANT'
                && $request['referenceId'] === 'PAY-DIRECT-1';
        });
    }

    public function test_checkout_menampilkan_kelompok_channel_ipaymu(): void
    {
        $this->createGateway();
        $product = $this->createProduct(12000);

        $this->get(route('checkout.show', $product))
            ->assertOk()
            ->assertSee('Pilih Channel iPaymu')
            ->assertSee('Virtual Account')
            ->assertSee('E-Wallet')
            ->assertSee('BCA')
            ->assertSee('DANA')
            ->assertSee('Alfamart');
    }

    public function test_checkout_ipaymu_di_bawah_minimum_ditolak_dengan_pesan_yang_jelas(): void
    {
        $this->createGateway();
        $product = $this->createProduct(1530);
        $user = User::factory()->create(['phone' => '081234567890']);

        $this->actingAs($user)
            ->from(route('checkout.show', $product))
            ->post(route('checkout.store'), [
                'product_id' => $product->id,
                'target_user_id' => '12345678',
                'target_zone' => '1234',
                'quantity' => 1,
                'gateway_code' => 'ipaymu',
            ])
            ->assertRedirect(route('checkout.show', $product))
            ->assertSessionHasErrors(['checkout' => 'Minimal channel iPaymu ini adalah Rp 10.000. Tingkatkan jumlah pesanan menjadi minimal 7. Atau gunakan Saldo Member.']);

        $this->assertDatabaseCount('transactions', 0);
        Http::assertNothingSent();
    }

    public function test_checkout_ipaymu_menerima_session_id_resmi_dan_menyimpan_url_pembayaran(): void
    {
        Http::fake([
            'sandbox.ipaymu.com/api/v2/payment/direct' => Http::response([
                'Status' => 200,
                'Message' => 'Success',
                'Data' => [
                    'TransactionId' => 'checkout-session-123',
                    'Via' => 'qris',
                    'Channel' => 'mpm',
                    'PaymentNo' => 'QRIS-123456',
                    'PaymentName' => 'QRIS',
                    'Url' => 'https://sandbox.ipaymu.com/payment/checkout-session-123',
                ],
            ]),
        ]);
        $this->createGateway();
        $product = $this->createProduct(1530);
        $user = User::factory()->create(['phone' => '081234567890']);

        $response = $this->actingAs($user)->post(route('checkout.store'), [
            'product_id' => $product->id,
            'target_user_id' => '12345678',
            'target_zone' => '1234',
            'quantity' => 7,
            'gateway_code' => 'ipaymu',
            'ipaymu_method' => 'qris',
            'ipaymu_channel' => 'mpm',
        ]);

        $transaction = Transaction::firstOrFail();
        $response->assertRedirect(route('payment.show', $transaction->invoice_code));
        $this->assertSame(10710, $transaction->total_amount);
        $this->assertSame('checkout-session-123', $transaction->payment_payload['_gateway_reference']);
        $this->assertSame('https://sandbox.ipaymu.com/payment/checkout-session-123', $transaction->payment_payload['_checkout_url']);

        $this->get(route('payment.show', $transaction->invoice_code))
            ->assertOk()
            ->assertSee('Channel pembayaran')
            ->assertSee('QRIS-123456')
            ->assertSee('Bayar Sekarang');

        Http::assertSent(function (Request $request): bool {
            $data = $request->data();

            return ! array_key_exists('account', $data)
                && $data['paymentMethod'] === 'qris'
                && $data['paymentChannel'] === 'mpm'
                && $request->url() === 'https://sandbox.ipaymu.com/api/v2/payment/direct';
        });
    }

    public function test_topup_ipaymu_menyimpan_url_checkout_dan_bisa_membuka_invoice(): void
    {
        Http::fake([
            'sandbox.ipaymu.com/api/v2/payment' => Http::response([
                'Status' => 200,
                'Message' => 'Success',
                'Data' => [
                    'SessionId' => 'session-456',
                    'Url' => 'https://sandbox.ipaymu.com/payment/session-456',
                ],
            ]),
        ]);
        $user = User::factory()->create(['phone' => '081234567890']);
        $this->createGateway();

        $response = $this->actingAs($user)->post(route('topup.store'), [
            'amount' => 50000,
            'gateway_code' => 'ipaymu',
        ]);

        $transaction = Transaction::firstOrFail();
        $response->assertRedirect(route('payment.show', $transaction->invoice_code));
        $this->assertSame('081234567890', $transaction->buyer_phone);
        $this->assertSame('https://sandbox.ipaymu.com/payment/session-456', $transaction->payment_payload['_checkout_url']);

        $this->get(route('payment.show', $transaction->invoice_code))
            ->assertOk()
            ->assertSee('Topup Saldo')
            ->assertSee('Bayar Sekarang')
            ->assertSee('https://sandbox.ipaymu.com/payment/session-456', false);
    }

    private function createGateway(): PaymentGatewayConfig
    {
        return PaymentGatewayConfig::create([
            'code' => 'ipaymu',
            'name' => 'iPaymu',
            'gateway_class' => IPaymuGateway::class,
            'is_active' => true,
            'is_sandbox' => true,
            'sort_order' => 0,
            'credentials' => ['va' => '123456', 'secret' => 'api-secret'],
            'fee_flat' => 0,
            'fee_percent' => 0,
        ]);
    }

    private function createProduct(int $price): Product
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

        return Product::create([
            'supplier_config_id' => $supplier->id,
            'supplier_code' => 'ML-IPAYMU',
            'name' => '5 Diamonds',
            'game' => 'Mobile Legends',
            'product_type' => Product::TYPE_GAME,
            'cost_basic' => 1000,
            'cost_premium' => 1000,
            'cost_special' => 1000,
            'price_guest' => $price,
            'price_biasa' => $price,
            'price_vip' => $price,
            'is_active' => true,
            'in_stock' => true,
        ]);
    }
}
