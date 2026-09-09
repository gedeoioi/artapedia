<?php

namespace Tests\Feature;

use App\Models\PaymentGatewayConfig;
use App\Models\Transaction;
use App\Models\User;
use App\Payments\IPaymuGateway;
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
                    'SessionId' => 'session-123',
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
                && $data['account'] === '123456'
                && $data['product'] === ['Topup saldo 50000']
                && $data['qty'] === [1]
                && $data['price'] === [50000]
                && $data['phone'] === '081234567890'
                && $data['buyerPhone'] === '081234567890';
        });
    }

    public function test_topup_memerlukan_nomor_hp_yang_valid(): void
    {
        $user = User::factory()->create(['phone' => null]);
        $this->createGateway();

        $this->actingAs($user)
            ->from(route('topup.create'))
            ->post(route('topup.store'), [
                'amount' => 50000,
                'gateway_code' => 'ipaymu',
                'phone' => '',
            ])
            ->assertRedirect(route('topup.create'))
            ->assertSessionHasErrors('phone');

        Http::assertNothingSent();
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
        $user = User::factory()->create(['phone' => null]);
        $this->createGateway();

        $response = $this->actingAs($user)->post(route('topup.store'), [
            'amount' => 50000,
            'gateway_code' => 'ipaymu',
            'phone' => '081234567890',
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
}
