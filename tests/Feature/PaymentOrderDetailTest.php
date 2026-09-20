<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\SupplierConfig;
use App\Models\Transaction;
use App\Models\User;
use App\Suppliers\VipResellerProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Panel "Detail Pesanan" di halaman pembayaran harus tampil pada semua status,
 * bukan hanya setelah sukses — pembeli perlu memastikan datanya benar sejak
 * sebelum membayar.
 */
class PaymentOrderDetailTest extends TestCase
{
    use RefreshDatabase;

    protected function gameProduct(): Product
    {
        $supplier = SupplierConfig::firstOrCreate(
            ['code' => 'vip-reseller'],
            [
                'name' => 'VIP Reseller', 'provider_class' => VipResellerProvider::class,
                'is_active' => true, 'is_sandbox' => true, 'priority' => 0,
                'credentials' => ['api_id' => 'x', 'api_key' => 'y'],
            ],
        );

        return Product::firstOrCreate(
            ['supplier_config_id' => $supplier->id, 'supplier_code' => 'ML-5'],
            [
                'name' => '5 Diamonds (5 + 0 Bonus)', 'game' => 'Mobile Legends',
                'product_type' => Product::TYPE_GAME,
                'cost_basic' => 1000, 'cost_premium' => 1000, 'cost_special' => 1000,
                'price_guest' => 1529, 'price_biasa' => 1529, 'price_vip' => 1529,
                'is_active' => true, 'in_stock' => true,
            ],
        );
    }

    protected function transaction(array $overrides = []): Transaction
    {
        return Transaction::create(array_merge([
            'invoice_code' => 'INV-DETAIL-'.uniqid(),
            'user_id' => User::factory()->create()->id,
            'product_id' => $this->gameProduct()->id,
            'payment_gateway_code' => 'balance',
            'target_user_id' => '301545836',
            'target_zone' => '9575',
            'nickname' => 'The Macman™',
            'quantity' => 2,
            'cost_price' => 2000, 'sell_price' => 3058,
            'admin_fee' => 0, 'gateway_fee' => 0, 'total_amount' => 3058, 'profit' => 1058,
            'payment_method' => 'balance',
            'status' => Transaction::STATUS_PROCESSING,
            'paid_at' => now(),
        ], $overrides));
    }

    public function test_panel_detail_tampil_saat_status_processing(): void
    {
        $trx = $this->transaction(['status' => Transaction::STATUS_PROCESSING]);

        $response = $this->get(route('payment.show', $trx->invoice_code));

        $response->assertOk()
            ->assertSee('Detail Pesanan')
            ->assertSee('ID Game')
            ->assertSee('301545836')
            ->assertSee('Server / Zone: 9575')
            ->assertSee('Nickname')
            ->assertSee('The Macman')
            ->assertSee('Jumlah Pesanan')
            ->assertSee('2 item')
            ->assertSee('Total Pembayaran')
            ->assertSee('3.058')
            ->assertSee('Metode Pembayaran')
            ->assertSee('Saldo Member')
            ->assertSee('Status Transaksi')
            ->assertSee('Waktu Transaksi')
            ->assertSee('WIB');
    }

    /**
     * Ini inti permintaan: detail harus ada sejak status masih pending,
     * bukan hanya setelah transaksi sukses.
     *
     * Catatan perilaku yang sudah ada: invoice final (success/failed/expired)
     * dialihkan ke halaman /cek-invoice, yang juga sudah menampilkan detail
     * pesanan yang sama. Jadi yang diuji di sini adalah rentang status yang
     * benar-benar dirender oleh halaman pembayaran.
     */
    public function test_panel_detail_tampil_pada_status_berjalan(): void
    {
        $statuses = [
            Transaction::STATUS_PENDING,
            Transaction::STATUS_PAID,
            Transaction::STATUS_PROCESSING,
        ];

        foreach ($statuses as $status) {
            $trx = $this->transaction([
                'invoice_code' => 'INV-ST-'.strtoupper(substr($status, 0, 4)).'-'.uniqid(),
                'status' => $status,
                'paid_at' => $status === Transaction::STATUS_PENDING ? null : now(),
            ]);

            $html = $this->get(route('payment.show', $trx->invoice_code))->assertOk()->getContent();

            $this->assertStringContainsString('Detail Pesanan', $html, "Panel hilang pada status {$status}.");
            $this->assertStringContainsString('301545836', $html, "ID Game hilang pada status {$status}.");
            $this->assertStringContainsString('The Macman', $html, "Nickname hilang pada status {$status}.");
        }
    }

    /**
     * Invoice final dialihkan ke /cek-invoice — dan halaman itu WAJIB tetap
     * menampilkan detail pesanannya, supaya pembeli tidak kehilangan informasi
     * setelah transaksi selesai.
     */
    public function test_invoice_final_dialihkan_dan_detail_tetap_ada(): void
    {
        foreach ([Transaction::STATUS_SUCCESS, Transaction::STATUS_FAILED, Transaction::STATUS_EXPIRED] as $status) {
            $trx = $this->transaction([
                'invoice_code' => 'INV-FIN-'.strtoupper(substr($status, 0, 4)).'-'.uniqid(),
                'status' => $status,
                'paid_at' => $status === Transaction::STATUS_EXPIRED ? null : now(),
            ]);

            // Halaman pembayaran mengalihkan (perilaku lama yang dipertahankan).
            $this->get(route('payment.show', $trx->invoice_code))
                ->assertRedirect(route('invoice.show', ['code' => $trx->invoice_code]));

            // Halaman tujuan tetap menampilkan detail pesanan.
            $this->get(route('invoice.show', ['code' => $trx->invoice_code]))
                ->assertOk()
                ->assertSee('301545836')
                ->assertSee('The Macman')
                ->assertSee('3.058');
        }
    }

    public function test_label_tujuan_mengikuti_tipe_produk(): void
    {
        $supplier = SupplierConfig::firstOrCreate(
            ['code' => 'vip-reseller'],
            [
                'name' => 'VIP Reseller', 'provider_class' => VipResellerProvider::class,
                'is_active' => true, 'is_sandbox' => true, 'priority' => 0,
                'credentials' => ['api_id' => 'x', 'api_key' => 'y'],
            ],
        );
        $pulsa = Product::create([
            'supplier_config_id' => $supplier->id, 'supplier_code' => 'TSEL-10',
            'name' => 'Telkomsel 10.000', 'game' => 'Telkomsel',
            'product_type' => Product::TYPE_PULSA,
            'cost_basic' => 9500, 'cost_premium' => 9500, 'cost_special' => 9500,
            'price_guest' => 10500, 'price_biasa' => 10500, 'price_vip' => 10500,
            'is_active' => true, 'in_stock' => true,
        ]);

        $trx = $this->transaction([
            'invoice_code' => 'INV-PULSA-DETAIL',
            'product_id' => $pulsa->id,
            'target_user_id' => '081234567890',
            'target_zone' => null,
            'nickname' => null,
            'payment_method' => 'balance',
            'payment_gateway_code' => 'balance',
        ]);

        $this->get(route('payment.show', $trx->invoice_code))
            ->assertOk()
            ->assertSee('Nomor HP')
            ->assertSee('081234567890');
    }

    public function test_topup_saldo_tidak_menampilkan_id_game(): void
    {
        $user = User::factory()->create();

        $trx = Transaction::create([
            'invoice_code' => 'INV-TOPUP-DETAIL',
            'user_id' => $user->id,
            'product_id' => null,
            'payment_gateway_code' => 'xendit',
            'target_user_id' => 'TOPUP',
            'quantity' => 1,
            'cost_price' => 0, 'sell_price' => 50000,
            'admin_fee' => 0, 'gateway_fee' => 0, 'total_amount' => 50000, 'profit' => 0,
            'payment_method' => 'xendit',
            'payment_reference' => 'PAY-TOPUP-DETAIL',
            'status' => Transaction::STATUS_PENDING,
            'meta' => ['kind' => 'topup'],
        ]);

        $this->get(route('payment.show', $trx->invoice_code))
            ->assertOk()
            ->assertSee('Jenis Transaksi')
            ->assertSee('Topup Saldo')
            ->assertDontSee('ID Game')
            ->assertDontSee('Nickname');
    }

    public function test_metode_pembayaran_tidak_membocorkan_istilah_internal(): void
    {
        $trx = $this->transaction([
            'payment_method' => 'tripay',
            'payment_gateway_code' => 'tripay',
        ]);

        $html = $this->get(route('payment.show', $trx->invoice_code))->assertOk()->getContent();

        $this->assertStringNotContainsStringIgnoringCase('supplier', $html);
    }

    /**
     * Keterangan metode pembayaran sudah ada di grid Detail Pesanan, jadi baris
     * "Dibayar dengan saldo member." hanya mengulang informasi yang sama.
     */
    public function test_tidak_ada_keterangan_saldo_yang_mengulang(): void
    {
        $trx = $this->transaction(['payment_method' => 'balance', 'payment_gateway_code' => 'balance']);

        $html = $this->get(route('payment.show', $trx->invoice_code))->assertOk()->getContent();

        $this->assertStringNotContainsStringIgnoringCase('dibayar dengan saldo', $html);

        // Metode pembayaran tetap terlihat di grid — yang dihapus hanya pengulangannya.
        $this->assertStringContainsString('Metode Pembayaran', $html);
        $this->assertStringContainsString('Saldo Member', $html);
    }

    /**
     * Instruksi pembayaran hanya relevan untuk transaksi non-saldo. Transaksi
     * saldo sudah lunas saat dibuat, jadi tidak boleh menyuruh membayar lagi.
     */
    public function test_transaksi_saldo_tidak_menampilkan_instruksi_bayar(): void
    {
        $trx = $this->transaction(['payment_method' => 'balance', 'payment_gateway_code' => 'balance']);

        $html = $this->get(route('payment.show', $trx->invoice_code))->assertOk()->getContent();

        $this->assertStringNotContainsString('Selesaikan pembayaran', $html);
        $this->assertStringNotContainsString('Rincian tagihan pembayaran', $html);
    }

    public function test_transaksi_gateway_tetap_menampilkan_instruksi_bayar(): void
    {
        $trx = $this->transaction([
            'payment_method' => 'xendit',
            'payment_gateway_code' => 'xendit',
            'status' => Transaction::STATUS_PENDING,
            'paid_at' => null,
        ]);

        $html = $this->get(route('payment.show', $trx->invoice_code))->assertOk()->getContent();

        $this->assertStringContainsString('Selesaikan pembayaran', $html);
        $this->assertStringContainsString('Rincian tagihan pembayaran', $html);
        $this->assertStringContainsString('Total Bayar', $html);
    }

    public function test_status_di_panel_sinkron_dengan_badge(): void
    {
        $trx = $this->transaction(['status' => Transaction::STATUS_PROCESSING]);

        $html = $this->get(route('payment.show', $trx->invoice_code))->assertOk()->getContent();

        // Badge atas dan grid detail harus memakai label yang sama.
        $this->assertSame(2, substr_count($html, 'Sedang diproses'));
    }

    public function test_js_memperbarui_status_di_panel(): void
    {
        $trx = $this->transaction();

        $html = $this->get(route('payment.show', $trx->invoice_code))->assertOk()->getContent();

        // Elemen target harus ada dan JS harus menulis ke situ saat polling.
        $this->assertStringContainsString('id="order-status-value"', $html);
        $this->assertStringContainsString("getElementById('order-status-value')", $html);
    }

    public function test_payment_method_label_untuk_saldo_dan_gateway(): void
    {
        $this->assertSame('Saldo Member', $this->transaction(['payment_method' => 'balance'])->paymentMethodLabel());

        $manual = $this->transaction([
            'payment_method' => 'manual',
            'payment_gateway_code' => 'manual',
        ]);
        $this->assertSame('Transfer Manual', $manual->paymentMethodLabel());

        $xendit = $this->transaction([
            'payment_method' => 'xendit',
            'payment_gateway_code' => 'xendit',
        ]);
        $this->assertSame('Xendit', $xendit->paymentMethodLabel());
    }

    public function test_waktu_transaksi_dalam_wib(): void
    {
        $trx = $this->transaction();

        $this->assertNotNull($trx->localCreatedAt());
        $this->assertMatchesRegularExpression('/^\d{2}\/\d{2}\/\d{4}, \d{2}:\d{2}$/', $trx->localCreatedAt());
    }
}
