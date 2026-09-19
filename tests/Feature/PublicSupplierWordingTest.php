<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\SupplierConfig;
use App\Models\Transaction;
use App\Models\User;
use App\Models\WaNotificationSetting;
use App\Suppliers\VipResellerProvider;
use Database\Seeders\ArtaPediaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Kata "supplier" adalah istilah internal. Pembeli tidak boleh melihatnya di
 * mana pun — hanya dashboard admin yang boleh menampilkannya.
 *
 * Test ini menjaga aturan itu agar tidak bocor lagi lewat pesan status, JSON,
 * halaman publik, atau template notifikasi.
 */
class PublicSupplierWordingTest extends TestCase
{
    use RefreshDatabase;

    protected function product(): Product
    {
        $supplier = SupplierConfig::firstOrCreate(
            ['code' => 'vip-reseller'],
            [
                'name' => 'VIP Reseller', 'provider_class' => VipResellerProvider::class,
                'is_active' => true, 'is_sandbox' => true, 'priority' => 0,
                'credentials' => ['api_id' => 'x', 'api_key' => 'y'],
            ],
        );

        // firstOrCreate: helper ini dipanggil berkali-kali dalam satu test, dan
        // unique index (supplier_config_id, supplier_code) menolak duplikat.
        return Product::firstOrCreate(
            ['supplier_config_id' => $supplier->id, 'supplier_code' => 'ML-100'],
            [
                'name' => 'ML 100 Diamond', 'game' => 'Mobile Legends',
                'cost_basic' => 10000, 'cost_premium' => 9500, 'cost_special' => 9000,
                'price_guest' => 12000, 'price_biasa' => 11500, 'price_vip' => 11000,
                'is_active' => true, 'in_stock' => true,
            ],
        );
    }

    protected function transaction(string $status, ?string $supplierStatus = null): Transaction
    {
        return Transaction::create([
            'invoice_code' => 'INV-PUB-'.uniqid(),
            'user_id' => User::factory()->create()->id,
            'product_id' => $this->product()->id,
            'payment_gateway_code' => 'xendit',
            'target_user_id' => '12345678',
            'quantity' => 1, 'cost_price' => 10000, 'sell_price' => 12000,
            'admin_fee' => 0, 'gateway_fee' => 0, 'total_amount' => 12000, 'profit' => 2000,
            'payment_method' => 'xendit',
            'payment_reference' => 'PAY-'.uniqid(),
            'status' => $status,
            'supplier_status' => $supplierStatus,
        ]);
    }

    /**
     * Semua pesan status yang bisa dibaca pembeli, untuk setiap kombinasi
     * status transaksi dan status internal.
     */
    public function test_pesan_status_tidak_pernah_menyebut_supplier(): void
    {
        $cases = [
            [Transaction::STATUS_PENDING, null],
            [Transaction::STATUS_PAID, null],
            [Transaction::STATUS_PROCESSING, 'waiting'],
            [Transaction::STATUS_PROCESSING, 'processing'],
            [Transaction::STATUS_PROCESSING, 'proccessing'],
            [Transaction::STATUS_PROCESSING, 'all_suppliers_failed'],
            [Transaction::STATUS_PROCESSING, 'batch_1_of_3'],
            [Transaction::STATUS_SUCCESS, 'success'],
            [Transaction::STATUS_FAILED, 'failed'],
            [Transaction::STATUS_EXPIRED, null],
        ];

        foreach ($cases as [$status, $supplierStatus]) {
            $trx = $this->transaction($status, $supplierStatus);

            $message = $trx->statusMessage();
            $label = $trx->statusLabel();

            $this->assertStringNotContainsStringIgnoringCase(
                'supplier', $message,
                "statusMessage() bocor untuk status {$status}/{$supplierStatus}: {$message}",
            );
            $this->assertStringNotContainsStringIgnoringCase(
                'supplier', $label,
                "statusLabel() bocor untuk status {$status}: {$label}",
            );
        }
    }

    public function test_endpoint_status_publik_tidak_mengirim_supplier_status(): void
    {
        $trx = $this->transaction(Transaction::STATUS_PROCESSING, 'waiting');

        $json = $this->getJson("/pay/{$trx->invoice_code}/status")
            ->assertOk()
            ->json();

        $this->assertArrayNotHasKey('supplier_status', $json);
        $this->assertStringNotContainsStringIgnoringCase('supplier', json_encode($json));
    }

    public function test_halaman_publik_tidak_menampilkan_kata_supplier(): void
    {
        $product = $this->product();
        $trx = $this->transaction(Transaction::STATUS_PROCESSING, 'waiting');

        $pages = [
            '/',
            '/kategori',
            "/product/{$product->id}/checkout",
            '/cek-invoice',
            "/cek-invoice/hasil?code={$trx->invoice_code}",
            '/faq',
            '/kontak',
            '/terms-and-conditions',
            '/privacy-policy',
            '/refund-policy',
            '/game/Mobile%20Legends',
            '/review',
            "/pay/{$trx->invoice_code}",
        ];

        foreach ($pages as $path) {
            $html = $this->get($path)->assertOk()->getContent();

            $this->assertStringNotContainsStringIgnoringCase(
                'supplier', $html,
                "Halaman publik {$path} menampilkan kata 'supplier'.",
            );
        }
    }

    public function test_kalimat_cara_order_sesuai_permintaan(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('Diproses otomatis, pantau via Cek Transaksi.', $html);
        $this->assertStringNotContainsString('Diproses ke supplier', $html);
    }

    /**
     * Template notifikasi dikirim ke WhatsApp pembeli, jadi isinya juga publik.
     * Template default tidak boleh menyebut supplier.
     */
    public function test_template_wa_default_tidak_menyebut_supplier(): void
    {
        $this->seed(ArtaPediaSeeder::class);

        foreach (WaNotificationSetting::all() as $setting) {
            if ($setting->name === 'admin_alert') {
                // Kanal ini khusus admin, jadi istilah internal boleh dipakai.
                continue;
            }

            $this->assertStringNotContainsStringIgnoringCase(
                'supplier', (string) $setting->template,
                "Template WA '{$setting->name}' menyebut supplier padahal dikirim ke pembeli.",
            );
        }
    }

    public function test_pesan_cek_nickname_tidak_menyebut_supplier(): void
    {
        $product = $this->product();

        // Supplier aktif tapi kredensial kosong -> pesan "nonaktif".
        SupplierConfig::where('code', 'vip-reseller')->update(['is_active' => false]);

        $json = $this->postJson('/checkout/check-nickname', [
            'product_id' => $product->id,
            'user_id' => '12345678',
        ])->json();

        $this->assertFalse($json['ok']);
        $this->assertStringNotContainsStringIgnoringCase('supplier', (string) $json['message']);
        $this->assertStringNotContainsStringIgnoringCase('vipayment', (string) $json['message']);
    }

    public function test_pesan_checkout_tidak_menyebut_nama_supplier(): void
    {
        $product = $this->product();

        // Quantity di atas maksimum -> pesan penolakan.
        $response = $this->post('/checkout', [
            'product_id' => $product->id,
            'target_user_id' => '12345678',
            'gateway_code' => 'balance',
            'quantity' => 10,
            'buyer_phone' => '081234567890',
            'buyer_email' => 'budi@example.test',
        ]);

        $errors = session('errors')?->all() ?? [];
        $flattened = strtolower(implode(' ', $errors));

        $this->assertStringNotContainsString('supplier', $flattened);
        $this->assertStringNotContainsString('vipreseller', $flattened);
    }

    /**
     * Aturan berlaku untuk sisi publik saja. Dashboard admin justru HARUS tetap
     * memakai istilah "supplier" supaya operator tahu apa yang sedang terjadi.
     */
    public function test_admin_tetap_melihat_istilah_supplier(): void
    {
        $admin = User::factory()->create(['level' => 'admin']);
        $admin->assignRole(Role::firstOrCreate(['name' => 'super-admin']));

        foreach (['/admin/supplier-configs', '/admin/products'] as $path) {
            $this->actingAs($admin)->get($path)->assertOk();
        }

        // Kolom status internal tetap tersedia di tabel transaksi admin.
        $this->assertTrue(
            Schema::hasColumn('transactions', 'supplier_status'),
            'Kolom internal harus tetap ada untuk kebutuhan admin.',
        );

        $trx = $this->transaction(Transaction::STATUS_PROCESSING, 'waiting');
        $this->assertSame('waiting', $trx->supplier_status);
    }
}
