<?php

namespace Tests\Feature;

use App\Models\PaymentGatewayConfig;
use App\Models\Product;
use App\Models\SupplierConfig;
use App\Payments\TripayGateway;
use App\Services\PaymentService;
use App\Suppliers\VipResellerProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Biaya layanan harus mengikuti channel yang dipilih.
 *
 * Sebelumnya hanya iPaymu yang membaca tarif per grup channel, sehingga Tripay
 * selalu memakai tarif default — memilih QRIS atau Virtual Account menghasilkan
 * biaya layanan yang sama persis, dan pembeli tidak melihat bedanya.
 */
class GatewayFeePerChannelTest extends TestCase
{
    use RefreshDatabase;

    protected function gateway(string $code, array $over = []): PaymentGatewayConfig
    {
        return PaymentGatewayConfig::updateOrCreate(['code' => $code], array_merge([
            'name' => strtoupper($code),
            'gateway_class' => TripayGateway::class,
            'is_active' => true,
            'is_sandbox' => true,
            'sort_order' => 0,
            'fee_flat' => 0,
            'fee_percent' => 0,
            'channel_settings' => [
                'qris' => ['fee_flat' => 0, 'fee_percent' => 0.7],
                'va' => ['fee_flat' => 4000, 'fee_percent' => 0],
                'ewallet' => ['fee_flat' => 1500, 'fee_percent' => 1.5],
            ],
        ], $over));
    }

    protected function product(): Product
    {
        $s = SupplierConfig::firstOrCreate(
            ['code' => 'vip-reseller'],
            [
                'name' => 'VIP', 'provider_class' => VipResellerProvider::class,
                'is_active' => true, 'is_sandbox' => true, 'priority' => 0,
                'credentials' => ['api_id' => 'x', 'api_key' => 'y'],
            ],
        );

        return Product::create([
            'supplier_config_id' => $s->id, 'supplier_code' => 'ML-100',
            'name' => 'ML 100', 'game' => 'Mobile Legends',
            'cost_basic' => 10000, 'cost_premium' => 9500, 'cost_special' => 9000,
            'price_guest' => 22000, 'price_biasa' => 21500, 'price_vip' => 21000,
            'is_active' => true, 'in_stock' => true,
        ]);
    }

    /** Inti bug: dua channel berbeda harus menghasilkan biaya berbeda. */
    public function test_tripay_menghitung_biaya_berbeda_per_channel(): void
    {
        $gw = $this->gateway('tripay');
        $subtotal = 110_000;

        $qris = $gw->feeFor($subtotal, 'qris', 'QRIS');
        $va = $gw->feeFor($subtotal, 'va', 'BRIVA');

        $this->assertSame(770, $qris, 'QRIS = 0,7% x 110.000');
        $this->assertSame(4000, $va, 'VA = flat 4.000');
        $this->assertNotSame($qris, $va, 'biaya QRIS dan VA tidak boleh sama');
    }

    public function test_ipaymu_juga_menghitung_per_channel(): void
    {
        $gw = $this->gateway('ipaymu');
        $subtotal = 110_000;

        $this->assertSame(770, $gw->feeFor($subtotal, 'qris', 'QRIS'));
        $this->assertSame(4000, $gw->feeFor($subtotal, 'va', 'BRIVA'));
    }

    /** Tarif grup yang hanya mengisi salah satu kolom memakai default untuk sisanya. */
    public function test_tarif_grup_sebagian_memakai_default_untuk_sisanya(): void
    {
        $gw = $this->gateway('tripay', [
            'fee_flat' => 500,
            'fee_percent' => 1,
            'channel_settings' => ['qris' => ['fee_flat' => 0]],
        ]);

        // qris: flat grup (0) + persen default (1% x 10.000 = 100)
        $this->assertSame(100, $gw->feeFor(10_000, 'qris', 'QRIS'));
    }

    /** Tanpa tarif grup sama sekali, tarif default yang dipakai. */
    public function test_tanpa_tarif_grup_memakai_tarif_default(): void
    {
        $gw = $this->gateway('tripay', [
            'fee_flat' => 1000,
            'fee_percent' => 2,
            'channel_settings' => null,
        ]);

        $this->assertSame(1200, $gw->feeFor(10_000, 'qris', 'QRIS'));
        $this->assertFalse($gw->hasChannelRates());
    }

    public function test_has_channel_rates_mendeteksi_tarif_grup(): void
    {
        $this->assertTrue($this->gateway('tripay')->hasChannelRates());
        $this->assertFalse($this->gateway('xendit', ['channel_settings' => null])->hasChannelRates());
    }

    /**
     * Jalur sungguhan: quote checkout harus ikut berubah saat channel berganti.
     * Inilah yang dilihat pembeli di ringkasan pesanan.
     */
    public function test_quote_checkout_berubah_saat_channel_berganti(): void
    {
        $this->gateway('tripay');
        $product = $this->product();
        $payments = app(PaymentService::class);

        $qris = $payments->quote($product, null, 'tripay', 5, 'qris', 'QRIS');
        $va = $payments->quote($product, null, 'tripay', 5, 'va', 'BRIVA');

        $this->assertSame(110_000, $qris['subtotal']);
        $this->assertSame(770, $qris['gateway_fee']);
        $this->assertSame(4000, $va['gateway_fee']);
        $this->assertNotSame($qris['total'], $va['total'], 'total harus berbeda antar channel');

        $this->assertSame(110_770, $qris['total']);
        $this->assertSame(114_000, $va['total']);
    }

    /** Biaya harus ikut berubah saat jumlah pesanan berubah. */
    public function test_quote_berubah_saat_jumlah_diubah(): void
    {
        $this->gateway('tripay');
        $product = $this->product();
        $payments = app(PaymentService::class);

        $satu = $payments->quote($product, null, 'tripay', 1, 'qris', 'QRIS');
        $lima = $payments->quote($product, null, 'tripay', 5, 'qris', 'QRIS');

        $this->assertSame(22_000, $satu['subtotal']);
        $this->assertSame(154, $satu['gateway_fee'], '0,7% x 22.000');
        $this->assertSame(110_000, $lima['subtotal']);
        $this->assertSame(770, $lima['gateway_fee'], '0,7% x 110.000');
        $this->assertNotSame($satu['total'], $lima['total']);
    }
}
