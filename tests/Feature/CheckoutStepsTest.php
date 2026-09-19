<?php

namespace Tests\Feature;

use App\Models\PaymentGatewayConfig;
use App\Models\Product;
use App\Models\SupplierConfig;
use App\Models\User;
use App\Payments\TripayGateway;
use App\Suppliers\VipResellerProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CheckoutStepsTest extends TestCase
{
    use RefreshDatabase;

    protected function product(): Product
    {
        $supplier = SupplierConfig::firstOrCreate(
            ['code' => 'vip-reseller'],
            [
                'name' => 'VIP', 'provider_class' => VipResellerProvider::class,
                'is_active' => true, 'is_sandbox' => true, 'priority' => 0,
                'credentials' => ['api_id' => 'x', 'api_key' => 'y'],
            ],
        );

        return Product::create([
            'supplier_config_id' => $supplier->id, 'supplier_code' => 'ML-100',
            'name' => 'ML 100 Diamond', 'game' => 'Mobile Legends',
            'cost_basic' => 10000, 'cost_premium' => 9500, 'cost_special' => 9000,
            'price_guest' => 12000, 'price_biasa' => 11500, 'price_vip' => 11000,
            'is_active' => true, 'in_stock' => true,
        ]);
    }

    protected function activateTripay(): void
    {
        PaymentGatewayConfig::updateOrCreate(['code' => 'tripay'], [
            'name' => 'Tripay',
            'gateway_class' => TripayGateway::class,
            'is_active' => true,
            'is_sandbox' => true,
            'sort_order' => 0,
            'credentials' => ['api_key' => 'k', 'private_key' => 'p', 'merchant_code' => 'T0001'],
        ]);
    }

    public function test_halaman_checkout_menampilkan_lima_langkah_tanpa_angka(): void
    {
        $product = $this->product();

        $response = $this->get("/product/{$product->id}/checkout");

        $response->assertOk();

        // Kelima heading langkah tetap ada.
        $response->assertSee('Masukkan', false);
        $response->assertSee('Pilih Nominal');
        $response->assertSee('Pilih Pembayaran');
        $response->assertSee('Isi Kontak / WhatsApp');
        $response->assertSee('Konfirmasi');
        $response->assertSee('Bayar');

        $html = $response->getContent();

        // Jumlah heading langkah harus 5 (satu dengan class tambahan `mt-4`).
        $this->assertSame(5, substr_count($html, 'class="step-heading'));

        // Penanda angka langkah harus benar-benar hilang dari DOM.
        $this->assertStringNotContainsString('step-num', $html);
        foreach (['1', '2', '3', '4', '5'] as $number) {
            $this->assertStringNotContainsString('class="step-num">'.$number.'<', $html);
        }
    }

    /**
     * Heading langkah tidak boleh lagi menyisakan badge angka kosong — span
     * tanpa isi akan membuat jarak ganjil di sisi kiri judul.
     */
    public function test_heading_langkah_tidak_menyisakan_span_kosong(): void
    {
        $product = $this->product();

        $html = $this->get("/product/{$product->id}/checkout")->getContent();

        $this->assertStringNotContainsString('<div class="step-heading"><span></span>', $html);
        $this->assertSame(0, preg_match('/class="step-heading[^"]*">\s*<span>\s*<\/span>/', $html));
    }

    public function test_ringkasan_sticky_mobile_dirender(): void
    {
        $product = $this->product();

        $html = $this->get("/product/{$product->id}/checkout")->getContent();

        $this->assertStringContainsString('sticky-checkout-bar', $html);
        $this->assertStringContainsString('id="sticky-total"', $html);
        $this->assertStringContainsString('id="sticky-method"', $html);
        // Tombol sticky harus submit form checkout yang sama.
        $this->assertStringContainsString('form="checkout-form"', $html);
    }

    public function test_ringkasan_menampilkan_total_awal_dari_harga_level_user(): void
    {
        $product = $this->product();
        $vip = User::factory()->create(['level' => 'vip']);

        // Harga level VIP = 11.000, bukan harga guest 12.000.
        $this->actingAs($vip)
            ->get("/product/{$product->id}/checkout")
            ->assertOk()
            ->assertSee('11.000')
            ->assertDontSee('12.000');
    }

    public function test_panel_channel_tripay_muncul_saat_gateway_aktif(): void
    {
        $this->activateTripay();
        $product = $this->product();

        $response = $this->get("/product/{$product->id}/checkout");

        $response->assertOk();
        $response->assertSee('id="tripay-channel-panel"', false);
        $response->assertSee('tripay_channel', false);
        // Channel nyata dari daftar driver, bukan placeholder.
        $response->assertSee('QRIS');
        $response->assertSee('BRIVA');
    }

    public function test_panel_channel_tripay_tidak_muncul_saat_gateway_nonaktif(): void
    {
        $product = $this->product();

        $response = $this->get("/product/{$product->id}/checkout");

        $response->assertOk();
        $response->assertDontSee('id="tripay-channel-panel"', false);
    }

    public function test_kutipan_tripay_mengembalikan_fee_dan_total_dari_server(): void
    {
        $this->activateTripay();
        $product = $this->product();

        $response = $this->postJson('/checkout/quote', [
            'product_id' => $product->id,
            'gateway_code' => 'tripay',
            'tripay_channel' => 'QRIS',
            'quantity' => 1,
        ]);

        $response->assertOk();
        $json = $response->json();

        $this->assertSame(12000, $json['subtotal']);
        $this->assertArrayHasKey('gateway_fee', $json);
        $this->assertArrayHasKey('checkout_total', $json);
        $this->assertTrue($json['available']);
        // Total = subtotal + admin fee + fee gateway.
        $this->assertSame(
            $json['subtotal'] + $json['admin_fee'] + $json['gateway_fee'],
            $json['checkout_total'],
        );
    }

    public function test_kutipan_tripay_di_bawah_minimum_ditandai_tidak_tersedia(): void
    {
        $this->activateTripay();

        // Produk murah Rp 5.000, di bawah minimum Tripay Rp 10.000.
        $supplier = SupplierConfig::firstOrCreate(
            ['code' => 'vip-reseller'],
            [
                'name' => 'VIP', 'provider_class' => VipResellerProvider::class,
                'is_active' => true, 'is_sandbox' => true, 'priority' => 0,
                'credentials' => ['api_id' => 'x', 'api_key' => 'y'],
            ],
        );
        $cheap = Product::create([
            'supplier_config_id' => $supplier->id, 'supplier_code' => 'CHEAP-1',
            'name' => 'Voucher 5rb', 'game' => 'Voucher', 'category' => 'voucher',
            'cost_basic' => 4000, 'cost_premium' => 4000, 'cost_special' => 4000,
            'price_guest' => 5000, 'price_biasa' => 5000, 'price_vip' => 5000,
            'is_active' => true, 'in_stock' => true,
        ]);

        $json = $this->postJson('/checkout/quote', [
            'product_id' => $cheap->id,
            'gateway_code' => 'tripay',
            'tripay_channel' => 'QRIS',
        ])->assertOk()->json();

        $this->assertFalse($json['available']);
        $this->assertSame(TripayGateway::MINIMUM_AMOUNT, $json['minimum_amount']);
        $this->assertNotNull($json['message']);
    }

    public function test_gateway_tripay_tampil_di_daftar_pembayaran(): void
    {
        $this->activateTripay();
        $product = $this->product();

        $this->get("/product/{$product->id}/checkout")
            ->assertOk()
            ->assertSee('Tripay')
            ->assertSee('data-pay="tripay"', false);
    }
}
