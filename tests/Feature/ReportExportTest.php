<?php

namespace Tests\Feature;

use App\Filament\Pages\Reports;
use App\Models\Product;
use App\Models\SupplierConfig;
use App\Models\Transaction;
use App\Models\User;
use App\Services\ReportExportService;
use App\Services\ReportService;
use App\Suppliers\VipResellerProvider;
use App\Support\AdminRoles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ReportExportTest extends TestCase
{
    use RefreshDatabase;

    protected function admin(): User
    {
        foreach (AdminRoles::PERMISSIONS as $perm) {
            Permission::firstOrCreate(['name' => $perm]);
        }

        $user = User::factory()->create(['level' => 'admin']);
        $user->assignRole(Role::firstOrCreate(['name' => AdminRoles::SUPER_ADMIN]));

        return $user;
    }

    protected function product(string $name = 'ML 100 Diamond', string $category = 'game'): Product
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
            'supplier_config_id' => $supplier->id, 'supplier_code' => 'SKU-'.uniqid(),
            'name' => $name, 'game' => 'Mobile Legends', 'category' => $category,
            'cost_basic' => 10000, 'cost_premium' => 9500, 'cost_special' => 9000,
            'price_guest' => 12000, 'price_biasa' => 11500, 'price_vip' => 11000,
            'is_active' => true, 'in_stock' => true,
        ]);
    }

    protected function transaction(
        string $status,
        int $total,
        int $cost,
        int $gatewayFee = 0,
        ?Product $product = null,
        string $gateway = 'tripay',
        ?string $day = null,
    ): Transaction {
        $profit = $total - $cost - $gatewayFee;
        $at = $day ? now()->parse($day) : now();

        // created_at/updated_at tidak ada di $fillable, jadi harus di-set
        // setelah create. Kalau dioper lewat create() nilainya dibuang diam-diam
        // dan semua baris menumpuk di tanggal hari ini.
        $trx = Transaction::create([
            'invoice_code' => 'INV-RPT-'.uniqid(),
            'product_id' => $product?->id,
            'supplier_config_id' => $product?->supplier_config_id,
            'payment_gateway_code' => $gateway,
            'target_user_id' => '123',
            'quantity' => 1,
            'cost_price' => $cost,
            'sell_price' => $total,
            'admin_fee' => 0,
            'gateway_fee' => $gatewayFee,
            'total_amount' => $total,
            'profit' => $profit,
            'payment_method' => $gateway,
            'status' => $status,
            'paid_at' => $status === Transaction::STATUS_SUCCESS ? $at : null,
        ]);

        $trx->forceFill(['created_at' => $at, 'updated_at' => $at])->saveQuietly();

        return $trx;
    }

    public function test_ringkasan_hanya_menghitung_transaksi_sukses(): void
    {
        $product = $this->product();

        $this->transaction(Transaction::STATUS_SUCCESS, 12000, 10000, 0, $product);
        $this->transaction(Transaction::STATUS_SUCCESS, 12000, 10000, 0, $product);
        // Ini tidak boleh masuk omzet.
        $this->transaction(Transaction::STATUS_PENDING, 12000, 10000, 0, $product);
        $this->transaction(Transaction::STATUS_FAILED, 12000, 10000, 0, $product);
        $this->transaction(Transaction::STATUS_EXPIRED, 12000, 10000, 0, $product);

        $summary = app(ReportService::class)->summary(now()->startOfDay(), now()->endOfDay());

        $this->assertSame(2, $summary['count']);
        $this->assertSame(24000, $summary['revenue']);
        $this->assertSame(20000, $summary['cost']);
        $this->assertSame(4000, $summary['profit']);
        $this->assertSame(12000, $summary['average']);
    }

    /**
     * Fee gateway dibayar pelanggan sebagai bagian dari total tagihan lalu
     * diteruskan ke provider, jadi ia TIDAK mengurangi profit produk.
     * Kalau dikurangkan dua kali, labanya terhitung salah.
     */
    public function test_profit_bersih_tidak_mengurangkan_fee_gateway_dua_kali(): void
    {
        $product = $this->product();

        // total 12000 = harga 11000 + fee 1000 ; modal 10000
        $trx = $this->transaction(Transaction::STATUS_SUCCESS, 12000, 10000, 1000, $product);

        // Profit tersimpan = total − modal − fee = 1000 (sesuai model).
        $this->assertSame(1000, $trx->profit);

        $summary = app(ReportService::class)->summary(now()->startOfDay(), now()->endOfDay());

        $this->assertSame(12000, $summary['revenue']);
        $this->assertSame(10000, $summary['cost']);
        $this->assertSame(1000, $summary['gateway_fee']);
        // Profit laporan harus sama dengan yang tersimpan, bukan dikurangi lagi.
        $this->assertSame(1000, $summary['profit']);
    }

    public function test_breakdown_per_produk_dan_per_gateway(): void
    {
        $ml = $this->product('ML 100 Diamond', 'game');
        $ff = $this->product('FF 70 Diamond', 'game');

        $this->transaction(Transaction::STATUS_SUCCESS, 12000, 10000, 0, $ml, 'tripay');
        $this->transaction(Transaction::STATUS_SUCCESS, 12000, 10000, 0, $ml, 'tripay');
        $this->transaction(Transaction::STATUS_SUCCESS, 15000, 11000, 0, $ff, 'xendit');

        $reports = app(ReportService::class);

        $perProduct = $reports->breakdown('product', now()->startOfDay(), now()->endOfDay());
        $this->assertCount(2, $perProduct);
        $this->assertSame('ML 100 Diamond', $perProduct[0]->label);
        $this->assertSame(2, (int) $perProduct[0]->trx_count);
        $this->assertSame(24000, (int) $perProduct[0]->revenue);
        $this->assertSame(4000, (int) $perProduct[0]->profit);

        $perGateway = $reports->breakdown('gateway', now()->startOfDay(), now()->endOfDay());
        $labels = $perGateway->pluck('label')->all();
        $this->assertEqualsCanonicalizing(['tripay', 'xendit'], $labels);
    }

    public function test_breakdown_supplier_memisahkan_topup_manual(): void
    {
        $product = $this->product();

        $this->transaction(Transaction::STATUS_SUCCESS, 12000, 10000, 0, $product);
        // Topup saldo manual: tanpa produk & tanpa supplier.
        $this->transaction(Transaction::STATUS_SUCCESS, 50000, 0, 0, null, 'manual');

        $rows = app(ReportService::class)->breakdown('supplier', now()->startOfDay(), now()->endOfDay());
        $labels = $rows->pluck('label')->all();

        $this->assertContains('Manual / Tanpa Supplier', $labels);
        $this->assertContains('VIP', $labels);
    }

    public function test_dimensi_tidak_dikenal_ditolak(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        app(ReportService::class)->breakdown('password', now()->startOfDay(), now()->endOfDay());
    }

    public function test_rekap_harian_mengelompokkan_per_tanggal(): void
    {
        $product = $this->product();

        $this->transaction(Transaction::STATUS_SUCCESS, 12000, 10000, 0, $product, 'tripay', now()->toDateString());
        $this->transaction(Transaction::STATUS_SUCCESS, 12000, 10000, 0, $product, 'tripay', now()->subDay()->toDateString());

        $series = app(ReportService::class)->dailySeries(now()->subDays(3), now());

        $this->assertCount(2, $series);
        $this->assertSame(1, (int) $series[0]->trx_count);
        $this->assertSame(1, (int) $series[1]->trx_count);
    }

    public function test_layar_laporan_render_untuk_admin(): void
    {
        $this->transaction(Transaction::STATUS_SUCCESS, 12000, 10000, 0, $this->product());

        $this->actingAs($this->admin())->get('/admin/reports')->assertOk();

        Livewire::test(Reports::class)->assertOk();
    }

    public function test_operator_tidak_bisa_membuka_laporan(): void
    {
        foreach (AdminRoles::PERMISSIONS as $perm) {
            Permission::firstOrCreate(['name' => $perm]);
        }

        $operator = User::factory()->create(['level' => 'admin']);
        $operator->assignRole(Role::firstOrCreate(['name' => AdminRoles::OPERATOR]));

        $this->actingAs($operator)->get('/admin/reports')->assertForbidden();
        $this->actingAs($operator)->get('/admin/reports/print')->assertForbidden();
    }

    public function test_export_excel_menghasilkan_file_xlsx(): void
    {
        $this->transaction(Transaction::STATUS_SUCCESS, 12000, 10000, 0, $this->product());

        $response = $this->actingAs($this->admin())
            ->get('/admin/reports/print?dimension=product&from='.now()->startOfMonth()->toDateString().'&to='.now()->toDateString());

        $response->assertOk();
        $response->assertSee('Laporan Per Produk');
        $response->assertSee('ML 100 Diamond');

        // XLSX diuji lewat service supaya isi stream-nya benar-benar dibuat.
        $table = app(ReportService::class)->exportRows('product', now()->startOfMonth(), now());
        $streamed = app(ReportExportService::class)->xlsx($table, 'test.xlsx');

        ob_start();
        $streamed->sendContent();
        $binary = ob_get_clean();

        $this->assertNotEmpty($binary);
        // XLSX adalah arsip ZIP -> magic bytes "PK".
        $this->assertSame('PK', substr($binary, 0, 2));
    }

    public function test_halaman_cetak_menampilkan_profit(): void
    {
        $product = $this->product();
        $this->transaction(Transaction::STATUS_SUCCESS, 12000, 10000, 0, $product);

        $this->actingAs($this->admin())
            ->get('/admin/reports/print?dimension=product')
            ->assertOk()
            ->assertSee('Profit Bersih')
            ->assertSee('ML 100 Diamond');
    }

    public function test_dimensi_ngawur_di_url_cetak_ditolak(): void
    {
        $this->actingAs($this->admin())
            ->get('/admin/reports/print?dimension=drop-table')
            ->assertNotFound();
    }

    public function test_rentang_tanggal_terbalik_dinormalkan(): void
    {
        $exports = app(ReportExportService::class);

        [$from, $to] = $exports->resolveRange('2026-09-20', '2026-09-01');

        $this->assertTrue($from->lessThanOrEqualTo($to));
    }

    public function test_laporan_kosong_tidak_error(): void
    {
        $this->actingAs($this->admin())
            ->get('/admin/reports')
            ->assertOk()
            ->assertSee('Tidak ada transaksi sukses');
    }
}
