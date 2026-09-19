<?php

namespace Tests\Feature;

use App\Models\BalanceMutation;
use App\Models\Invoice;
use App\Models\PaymentGatewayConfig;
use App\Models\Product;
use App\Models\SupplierConfig;
use App\Models\Transaction;
use App\Models\User;
use App\Payments\TripayGateway;
use App\Suppliers\VipResellerProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TripayProviderTest extends TestCase
{
    use RefreshDatabase;

    protected function gateway(bool $sandbox = true): TripayGateway
    {
        return new TripayGateway([
            'api_key' => 'API-KEY-TEST',
            'private_key' => 'PRIVATE-KEY-TEST',
            'merchant_code' => 'T0001',
        ], $sandbox);
    }

    protected function configureTripay(bool $active = true): PaymentGatewayConfig
    {
        // Baris `tripay` sudah dibuat oleh migration, jadi updateOrCreate
        // (unique index di kolom code).
        return PaymentGatewayConfig::updateOrCreate(['code' => 'tripay'], [
            'name' => 'Tripay',
            'gateway_class' => TripayGateway::class,
            'is_active' => $active,
            'is_sandbox' => true,
            'sort_order' => 0,
            'credentials' => [
                'api_key' => 'API-KEY-TEST',
                'private_key' => 'PRIVATE-KEY-TEST',
                'merchant_code' => 'T0001',
            ],
        ]);
    }

    public function test_signature_mengikuti_rumus_resmi_tripay(): void
    {
        $gateway = $this->gateway();

        // Rumus resmi: HMAC-SHA256(merchantCode . merchantRef . amount, privateKey)
        $expected = hash_hmac('sha256', 'T0001INV-123150000', 'PRIVATE-KEY-TEST');

        $this->assertSame($expected, $gateway->signature('INV-123', 150000));
    }

    public function test_verifikasi_callback_memakai_body_mentah(): void
    {
        $gateway = $this->gateway();
        $rawBody = '{"reference":"T0001ABC","merchant_ref":"INV-1","status":"PAID","total_amount":15000}';
        $signature = hash_hmac('sha256', $rawBody, 'PRIVATE-KEY-TEST');

        $this->assertTrue($gateway->verifyCallback($rawBody, $signature));

        // Signature yang dihitung dari body yang di-encode ulang tidak boleh
        // dianggap sah — itu justru bug yang membuat callback asli ditolak.
        $reEncoded = json_encode(json_decode($rawBody, true));
        $this->assertFalse($gateway->verifyCallback($rawBody, hash_hmac('sha256', 'body-lain', 'PRIVATE-KEY-TEST')));
    }

    public function test_private_key_kosong_tidak_pernah_menganggap_valid(): void
    {
        $gateway = new TripayGateway(['api_key' => 'x', 'private_key' => '', 'merchant_code' => 'T0001']);

        $this->assertFalse($gateway->verifyCallback('{}', hash_hmac('sha256', '{}', '')));
    }

    public function test_create_payment_mengirim_signature_dan_menerima_checkout_url(): void
    {
        Http::fake([
            'tripay.co.id/*' => Http::response([
                'success' => true,
                'data' => [
                    'reference' => 'T0001ABCDEF',
                    'merchant_ref' => 'INV-TRIPAY-1',
                    'checkout_url' => 'https://tripay.co.id/checkout/T0001ABCDEF',
                    'status' => 'UNPAID',
                ],
            ]),
        ]);

        $result = $this->gateway()->createPayment([
            'reference_id' => 'INV-TRIPAY-1',
            'amount' => 25000,
            'description' => 'ML 100 Diamond',
            'customer_name' => 'Budi',
            'customer_email' => 'budi@example.test',
            'customer_phone' => '081234567890',
            'payment_channel' => 'QRIS',
            'success_url' => 'https://artapedia.test/pay/INV-TRIPAY-1',
        ]);

        $this->assertTrue($result['ok']);
        $this->assertSame('T0001ABCDEF', $result['gateway_ref']);
        $this->assertSame('https://tripay.co.id/checkout/T0001ABCDEF', $result['pay_url']);

        Http::assertSent(function ($request) {
            $body = $request->data();

            return str_contains($request->url(), '/transaction/create')
                && $request->hasHeader('Authorization', 'Bearer API-KEY-TEST')
                && $body['method'] === 'QRIS'
                && $body['merchant_ref'] === 'INV-TRIPAY-1'
                && $body['amount'] === 25000
                // Signature harus persis rumus resmi.
                && $body['signature'] === hash_hmac('sha256', 'T0001INV-TRIPAY-125000', 'PRIVATE-KEY-TEST')
                && $body['callback_url'] === url('/webhook/payment/tripay');
        });
    }

    public function test_create_payment_menolak_channel_yang_tidak_dikenal(): void
    {
        Http::fake();

        $result = $this->gateway()->createPayment([
            'reference_id' => 'INV-X',
            'amount' => 25000,
            'payment_channel' => 'CHANNEL-NGAWUR',
        ]);

        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('tidak dikenal', $result['message']);
        Http::assertNothingSent();
    }

    public function test_create_payment_tanpa_kredensial_tidak_memanggil_api(): void
    {
        Http::fake();

        $gateway = new TripayGateway(['api_key' => '', 'private_key' => '', 'merchant_code' => '']);

        $result = $gateway->createPayment([
            'reference_id' => 'INV-X', 'amount' => 25000, 'payment_channel' => 'QRIS',
        ]);

        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('belum dikonfigurasi', $result['message']);
        Http::assertNothingSent();
    }

    public function test_webhook_signature_valid_melunasi_invoice_topup(): void
    {
        $this->configureTripay();
        $user = User::factory()->create(['balance' => 0, 'phone' => '081234567890']);

        $trx = Transaction::create([
            'invoice_code' => 'INV-TRIPAY-PAID', 'user_id' => $user->id,
            'payment_gateway_code' => 'tripay', 'target_user_id' => 'TOPUP',
            'quantity' => 1, 'cost_price' => 0, 'sell_price' => 50000,
            'admin_fee' => 0, 'gateway_fee' => 0, 'total_amount' => 50000, 'profit' => 0,
            'payment_method' => 'tripay', 'payment_reference' => 'INV-TRIPAY-PAID',
            'status' => Transaction::STATUS_PENDING, 'meta' => ['kind' => 'topup'],
        ]);

        Invoice::create([
            'transaction_id' => $trx->id, 'invoice_code' => 'INV-TRIPAY-PAID',
            'gateway_code' => 'tripay', 'reference_id' => 'INV-TRIPAY-PAID',
            'amount' => 50000, 'status' => 'pending', 'expired_at' => now()->addHour(),
        ]);

        $payload = [
            'reference' => 'T0001ABCDEF',
            'merchant_ref' => 'INV-TRIPAY-PAID',
            'status' => 'PAID',
            'total_amount' => 50000,
        ];
        $rawBody = json_encode($payload, JSON_UNESCAPED_SLASHES);
        $signature = hash_hmac('sha256', $rawBody, 'PRIVATE-KEY-TEST');

        $this->call(
            'POST',
            '/webhook/payment/tripay',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_CALLBACK_SIGNATURE' => $signature,
                'HTTP_X_CALLBACK_EVENT' => 'payment_status',
            ],
            $rawBody,
        )->assertOk()->assertJson(['ok' => true]);

        $this->assertSame(50000, $user->fresh()->balance);
        $this->assertSame(Transaction::STATUS_SUCCESS, $trx->fresh()->status);
        $this->assertSame(1, BalanceMutation::where('transaction_id', $trx->id)->count());
    }

    public function test_webhook_signature_salah_ditolak_dan_saldo_tidak_berubah(): void
    {
        $this->configureTripay();
        $user = User::factory()->create(['balance' => 0, 'phone' => '081234567890']);

        Transaction::create([
            'invoice_code' => 'INV-TRIPAY-BAD', 'user_id' => $user->id,
            'payment_gateway_code' => 'tripay', 'target_user_id' => 'TOPUP',
            'quantity' => 1, 'cost_price' => 0, 'sell_price' => 50000,
            'admin_fee' => 0, 'gateway_fee' => 0, 'total_amount' => 50000, 'profit' => 0,
            'payment_method' => 'tripay', 'payment_reference' => 'INV-TRIPAY-BAD',
            'status' => Transaction::STATUS_PENDING, 'meta' => ['kind' => 'topup'],
        ]);

        $rawBody = json_encode(['merchant_ref' => 'INV-TRIPAY-BAD', 'status' => 'PAID', 'total_amount' => 50000]);

        $this->call(
            'POST',
            '/webhook/payment/tripay',
            [], [], [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_CALLBACK_SIGNATURE' => 'signature-palsu',
                'HTTP_X_CALLBACK_EVENT' => 'payment_status',
            ],
            $rawBody,
        )->assertStatus(400);

        $this->assertSame(0, $user->fresh()->balance);
        $this->assertSame(0, BalanceMutation::count());
    }

    public function test_webhook_tanpa_signature_ditolak(): void
    {
        $this->configureTripay();

        $this->call(
            'POST',
            '/webhook/payment/tripay',
            [], [], [],
            ['CONTENT_TYPE' => 'application/json', 'HTTP_X_CALLBACK_EVENT' => 'payment_status'],
            json_encode(['merchant_ref' => 'INV-ANY', 'status' => 'PAID']),
        )->assertStatus(400);
    }

    public function test_webhook_duplikat_tidak_menambah_saldo_dua_kali(): void
    {
        $this->configureTripay();
        $user = User::factory()->create(['balance' => 0, 'phone' => '081234567890']);

        $trx = Transaction::create([
            'invoice_code' => 'INV-TRIPAY-DUP', 'user_id' => $user->id,
            'payment_gateway_code' => 'tripay', 'target_user_id' => 'TOPUP',
            'quantity' => 1, 'cost_price' => 0, 'sell_price' => 50000,
            'admin_fee' => 0, 'gateway_fee' => 0, 'total_amount' => 50000, 'profit' => 0,
            'payment_method' => 'tripay', 'payment_reference' => 'INV-TRIPAY-DUP',
            'status' => Transaction::STATUS_PENDING, 'meta' => ['kind' => 'topup'],
        ]);

        $rawBody = json_encode(['merchant_ref' => 'INV-TRIPAY-DUP', 'status' => 'PAID', 'total_amount' => 50000]);
        $signature = hash_hmac('sha256', $rawBody, 'PRIVATE-KEY-TEST');
        $headers = [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_CALLBACK_SIGNATURE' => $signature,
            'HTTP_X_CALLBACK_EVENT' => 'payment_status',
        ];

        $this->call('POST', '/webhook/payment/tripay', [], [], [], $headers, $rawBody)->assertOk();
        $this->call('POST', '/webhook/payment/tripay', [], [], [], $headers, $rawBody)->assertOk();
        $this->call('POST', '/webhook/payment/tripay', [], [], [], $headers, $rawBody)->assertOk();

        $this->assertSame(50000, $user->fresh()->balance);
        $this->assertSame(1, BalanceMutation::where('transaction_id', $trx->id)->count());
    }

    public function test_status_bukan_paid_tidak_melunasi(): void
    {
        $this->configureTripay();
        $user = User::factory()->create(['balance' => 0, 'phone' => '081234567890']);

        $trx = Transaction::create([
            'invoice_code' => 'INV-TRIPAY-UNPAID', 'user_id' => $user->id,
            'payment_gateway_code' => 'tripay', 'target_user_id' => 'TOPUP',
            'quantity' => 1, 'cost_price' => 0, 'sell_price' => 50000,
            'admin_fee' => 0, 'gateway_fee' => 0, 'total_amount' => 50000, 'profit' => 0,
            'payment_method' => 'tripay', 'payment_reference' => 'INV-TRIPAY-UNPAID',
            'status' => Transaction::STATUS_PENDING, 'meta' => ['kind' => 'topup'],
        ]);

        $rawBody = json_encode(['merchant_ref' => 'INV-TRIPAY-UNPAID', 'status' => 'UNPAID', 'total_amount' => 50000]);
        $signature = hash_hmac('sha256', $rawBody, 'PRIVATE-KEY-TEST');

        $this->call('POST', '/webhook/payment/tripay', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_CALLBACK_SIGNATURE' => $signature,
            'HTTP_X_CALLBACK_EVENT' => 'payment_status',
        ], $rawBody)->assertOk();

        $this->assertSame(0, $user->fresh()->balance);
        $this->assertSame(Transaction::STATUS_PENDING, $trx->fresh()->status);
    }

    public function test_nominal_callback_tidak_sesuai_ditolak(): void
    {
        $this->configureTripay();
        $user = User::factory()->create(['balance' => 0, 'phone' => '081234567890']);

        Transaction::create([
            'invoice_code' => 'INV-TRIPAY-AMT', 'user_id' => $user->id,
            'payment_gateway_code' => 'tripay', 'target_user_id' => 'TOPUP',
            'quantity' => 1, 'cost_price' => 0, 'sell_price' => 50000,
            'admin_fee' => 0, 'gateway_fee' => 0, 'total_amount' => 50000, 'profit' => 0,
            'payment_method' => 'tripay', 'payment_reference' => 'INV-TRIPAY-AMT',
            'status' => Transaction::STATUS_PENDING, 'meta' => ['kind' => 'topup'],
        ]);

        // Gateway mengaku dibayar 1.000 untuk invoice 50.000.
        $rawBody = json_encode(['merchant_ref' => 'INV-TRIPAY-AMT', 'status' => 'PAID', 'total_amount' => 1000]);
        $signature = hash_hmac('sha256', $rawBody, 'PRIVATE-KEY-TEST');

        $this->call('POST', '/webhook/payment/tripay', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_CALLBACK_SIGNATURE' => $signature,
            'HTTP_X_CALLBACK_EVENT' => 'payment_status',
        ], $rawBody)->assertStatus(422);

        $this->assertSame(0, $user->fresh()->balance);
    }

    public function test_checkout_tripay_membuat_transaksi_dan_memakai_channel_yang_dipilih(): void
    {
        $this->configureTripay();

        $supplier = SupplierConfig::create([
            'code' => 'vip-reseller', 'name' => 'VIP', 'provider_class' => VipResellerProvider::class,
            'is_active' => true, 'is_sandbox' => true, 'priority' => 0,
            'credentials' => ['api_id' => 'x', 'api_key' => 'y'],
        ]);
        $product = Product::create([
            'supplier_config_id' => $supplier->id, 'supplier_code' => 'ML-100',
            'name' => 'ML 100 Diamond', 'game' => 'Mobile Legends',
            'cost_basic' => 10000, 'cost_premium' => 9500, 'cost_special' => 9000,
            'price_guest' => 12000, 'price_biasa' => 11500, 'price_vip' => 11000,
            'is_active' => true, 'in_stock' => true,
        ]);

        Http::fake([
            'tripay.co.id/*' => Http::response([
                'success' => true,
                'data' => [
                    'reference' => 'T0001ZZZ',
                    'checkout_url' => 'https://tripay.co.id/checkout/T0001ZZZ',
                    'status' => 'UNPAID',
                ],
            ]),
        ]);

        $response = $this->post('/checkout', [
            'product_id' => $product->id,
            'target_user_id' => '123456789',
            'gateway_code' => 'tripay',
            'tripay_channel' => 'QRIS',
            'buyer_phone' => '081234567890',
            'buyer_email' => 'budi@example.test',
        ]);

        $response->assertRedirect();

        $trx = Transaction::first();
        $this->assertSame('tripay', $trx->payment_gateway_code);
        $this->assertSame(Transaction::STATUS_PENDING, $trx->status);

        Http::assertSent(fn ($request) => ($request->data()['method'] ?? null) === 'QRIS');
    }

    public function test_checkout_tripay_menolak_channel_ngawur(): void
    {
        $this->configureTripay();

        $supplier = SupplierConfig::create([
            'code' => 'vip-reseller', 'name' => 'VIP', 'provider_class' => VipResellerProvider::class,
            'is_active' => true, 'is_sandbox' => true, 'priority' => 0,
            'credentials' => ['api_id' => 'x', 'api_key' => 'y'],
        ]);
        $product = Product::create([
            'supplier_config_id' => $supplier->id, 'supplier_code' => 'ML-100',
            'name' => 'ML 100 Diamond', 'game' => 'Mobile Legends',
            'cost_basic' => 10000, 'cost_premium' => 9500, 'cost_special' => 9000,
            'price_guest' => 12000, 'price_biasa' => 11500, 'price_vip' => 11000,
            'is_active' => true, 'in_stock' => true,
        ]);

        Http::fake();

        $this->post('/checkout', [
            'product_id' => $product->id,
            'target_user_id' => '123456789',
            'gateway_code' => 'tripay',
            'tripay_channel' => 'BANK-NGAWUR',
            'buyer_phone' => '081234567890',
            'buyer_email' => 'budi@example.test',
        ])->assertSessionHasErrors('checkout');

        $this->assertSame(0, Transaction::count());
        Http::assertNothingSent();
    }

    public function test_gateway_tidak_aktif_tidak_bisa_dipakai_checkout(): void
    {
        $this->configureTripay(active: false);

        $supplier = SupplierConfig::create([
            'code' => 'vip-reseller', 'name' => 'VIP', 'provider_class' => VipResellerProvider::class,
            'is_active' => true, 'is_sandbox' => true, 'priority' => 0,
            'credentials' => ['api_id' => 'x', 'api_key' => 'y'],
        ]);
        $product = Product::create([
            'supplier_config_id' => $supplier->id, 'supplier_code' => 'ML-100',
            'name' => 'ML 100 Diamond', 'game' => 'Mobile Legends',
            'cost_basic' => 10000, 'cost_premium' => 9500, 'cost_special' => 9000,
            'price_guest' => 12000, 'price_biasa' => 11500, 'price_vip' => 11000,
            'is_active' => true, 'in_stock' => true,
        ]);

        Http::fake();

        $this->post('/checkout', [
            'product_id' => $product->id,
            'target_user_id' => '123456789',
            'gateway_code' => 'tripay',
            'tripay_channel' => 'QRIS',
            'buyer_phone' => '081234567890',
            'buyer_email' => 'budi@example.test',
        ])->assertSessionHasErrors('checkout');

        $this->assertSame(0, Transaction::count());
    }

    public function test_kredensial_tripay_tersimpan_terenkripsi(): void
    {
        $config = $this->configureTripay();

        $raw = \Illuminate\Support\Facades\DB::table('payment_gateway_configs')
            ->where('id', $config->id)
            ->value('credentials');

        // Rahasia tidak boleh terbaca sebagai plaintext di kolom DB.
        $this->assertStringNotContainsString('PRIVATE-KEY-TEST', (string) $raw);
        $this->assertSame('PRIVATE-KEY-TEST', $config->fresh()->credentials['private_key']);
    }
}
