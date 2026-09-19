<?php

namespace Tests\Feature;

use App\Models\BalanceMutation;
use App\Models\BalanceReconciliation;
use App\Models\Product;
use App\Models\SupplierConfig;
use App\Models\Transaction;
use App\Models\User;
use App\Models\WaNotificationSetting;
use App\Services\BalanceService;
use App\Suppliers\VipResellerProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BalanceReconcileTest extends TestCase
{
    use RefreshDatabase;

    public function test_laporan_bersih_saat_ledger_dan_cache_sama(): void
    {
        $user = User::factory()->create(['balance' => 0]);
        app(BalanceService::class)->credit($user, 50000, BalanceMutation::TYPE_TOPUP, 'topup test');

        $this->artisan('balance:reconcile')->assertSuccessful();

        $this->assertSame(50000, $user->fresh()->balance);
        $this->assertSame(0, BalanceReconciliation::count(), 'Tidak ada selisih, jadi tidak ada baris laporan.');
    }

    public function test_selisih_terdeteksi_dan_keluar_dengan_kode_gagal(): void
    {
        $user = User::factory()->create(['balance' => 0]);
        app(BalanceService::class)->credit($user, 50000, BalanceMutation::TYPE_TOPUP, 'topup test');

        // Rusak cache-nya langsung di DB, meniru bug tulis saldo di luar ledger.
        DB::table('users')->where('id', $user->id)->update(['balance' => 60000]);

        $this->artisan('balance:reconcile')->assertFailed();

        $this->assertSame(1, BalanceReconciliation::count());
        $row = BalanceReconciliation::first();
        $this->assertSame(50000, $row->ledger_total);
        $this->assertSame(60000, $row->cached_balance);
        $this->assertSame(10000, $row->difference);
    }

    /**
     * Perbaikan yang benar adalah menyamakan cache dengan ledger. Menambah baris
     * ledger sebesar selisih akan menggeser SUM(ledger) DAN cache dengan jumlah
     * yang sama, sehingga selisihnya tetap utuh — karena itu test ini memeriksa
     * hasilnya dengan menjalankan ulang, bukan sekali jalan.
     */
    public function test_fix_menyamakan_cache_dengan_ledger_dan_terbukti_di_jalan_kedua(): void
    {
        $user = User::factory()->create(['balance' => 0]);
        app(BalanceService::class)->credit($user, 50000, BalanceMutation::TYPE_TOPUP, 'topup test');
        DB::table('users')->where('id', $user->id)->update(['balance' => 60000]);

        $ledgerBefore = (int) BalanceMutation::where('user_id', $user->id)->sum('amount');

        $this->artisan('balance:reconcile --fix')->assertSuccessful();

        $this->assertSame(50000, $user->fresh()->balance, 'Cache harus disamakan dengan ledger.');

        // SUM(ledger) tidak boleh berubah — kalau berubah, "perbaikan"-nya salah.
        $this->assertSame(
            $ledgerBefore,
            (int) BalanceMutation::where('user_id', $user->id)->sum('amount'),
            'SUM(ledger) harus tetap sama setelah perbaikan.',
        );

        // Baris penyeimbang amount=0 supaya invarian "setiap perubahan saldo
        // punya baris ledger" tetap berlaku.
        $balancer = BalanceMutation::where('user_id', $user->id)->latest('id')->first();
        $this->assertSame(0, $balancer->amount);
        $this->assertSame(60000, $balancer->balance_before);
        $this->assertSame(50000, $balancer->balance_after);

        // Jalan kedua harus bersih. Ini yang membedakan perbaikan nyata dari
        // perbaikan yang hanya terlihat benar pada satu kali jalan.
        // (Tabel laporan bersifat riwayat, jadi baris selisih dari jalan pertama
        // tetap tersimpan — yang diperiksa adalah tidak ada selisih BARU.)
        $this->artisan('balance:reconcile')->assertSuccessful();
        $this->assertSame(
            0,
            BalanceReconciliation::where('action', 'none')->where('difference', '!=', 0)->count(),
            'Jalan kedua tidak boleh menemukan selisih baru.',
        );
    }

    /**
     * Transaksi berstatus final tanpa satu pun baris ledger tidak terlihat oleh
     * perbandingan saldo (cache dan ledger sama-sama setuju). Angka ini harus
     * dilaporkan terpisah supaya bug "order tidak pernah didebit" tetap ketahuan.
     */
    public function test_transaksi_tanpa_baris_ledger_dilaporkan_terpisah(): void
    {
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

        // Order sukses tapi tanpa debit ledger sama sekali.
        Transaction::create([
            'invoice_code' => 'INV-UNBACKED-1', 'product_id' => $product->id,
            'supplier_config_id' => $supplier->id,
            'payment_gateway_code' => 'xendit', 'target_user_id' => '123',
            'quantity' => 1, 'cost_price' => 10000, 'sell_price' => 12000,
            'admin_fee' => 0, 'gateway_fee' => 0, 'total_amount' => 12000, 'profit' => 2000,
            'payment_method' => 'xendit', 'status' => Transaction::STATUS_SUCCESS,
            'paid_at' => now(),
        ]);

        $user = User::factory()->create(['balance' => 0]);
        app(BalanceService::class)->credit($user, 50000, BalanceMutation::TYPE_TOPUP, 'topup');

        // Saldo seimbang, jadi tidak ada "selisih" — tapi transaksi tanpa ledger
        // tetap harus dilaporkan lewat output command.
        $this->artisan('balance:reconcile')
            ->expectsOutputToContain('Transaksi tanpa baris ledger: 1')
            ->expectsOutputToContain('TANPA baris ledger')
            ->assertSuccessful();

        $this->assertSame(0, BalanceReconciliation::count());
    }

    public function test_alert_wa_terkirim_ke_admin_saat_ada_selisih(): void
    {
        // Baris `admin_alert` sudah dibuat oleh migration, jadi pakai
        // updateOrCreate alih-alih create (unique index di kolom name).
        WaNotificationSetting::updateOrCreate(['name' => 'admin_alert'], [
            'is_active' => true,
            'recipient' => '081234567890',
            'template' => '{message}',
            'schedule' => 'on_event',
            'api_url' => 'https://wa.example.test/send',
        ]);

        $admin = User::factory()->create(['level' => 'admin', 'whatsapp' => '081298765432', 'balance' => 0]);
        app(BalanceService::class)->credit($admin, 10000, BalanceMutation::TYPE_TOPUP, 'topup');
        DB::table('users')->where('id', $admin->id)->update(['balance' => 99999]);

        Http::fake(['wa.example.test/*' => Http::response(['ok' => true])]);

        $this->artisan('balance:reconcile --alert')->assertFailed();

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'wa.example.test')
                && str_contains($request['message'], 'REKONSILIASI SALDO');
        });

        // Nomor lokal harus dinormalkan ke format 62xx, dan tidak boleh dobel.
        $sentTo = collect(Http::recorded())
            ->map(fn ($pair) => $pair[0]['to'])
            ->unique()
            ->values()
            ->all();
        $this->assertEqualsCanonicalizing(['6281234567890', '6281298765432'], $sentTo);

        $this->assertSame(1, BalanceReconciliation::where('alerted', true)->count());
    }

    public function test_alert_tidak_dikirim_saat_saldo_sehat(): void
    {
        WaNotificationSetting::updateOrCreate(['name' => 'admin_alert'], [
            'is_active' => true,
            'recipient' => '081234567890',
            'template' => '{message}',
            'schedule' => 'on_event',
            'api_url' => 'https://wa.example.test/send',
        ]);

        $user = User::factory()->create(['balance' => 0]);
        app(BalanceService::class)->credit($user, 10000, BalanceMutation::TYPE_TOPUP, 'topup');

        Http::fake();

        $this->artisan('balance:reconcile --alert')->assertSuccessful();

        Http::assertNothingSent();
    }

    public function test_user_tanpa_mutasi_sama_sekali_tidak_dianggap_selisih(): void
    {
        User::factory()->create(['balance' => 0]);

        $this->artisan('balance:reconcile')->assertSuccessful();
        $this->assertSame(0, BalanceReconciliation::count());
    }
}
