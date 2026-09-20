<?php

namespace Tests\Feature;

use App\Filament\Resources\SupplierConfigs\Pages\ListSupplierConfigs;
use App\Models\SupplierConfig;
use App\Models\User;
use App\Services\SupplierConnectionTester;
use App\Suppliers\VipResellerProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Log tes koneksi memuat respons MENTAH dari API supplier. Kalau respons itu
 * mengandung byte yang bukan UTF-8 valid, MySQL menolak seluruh UPDATE dengan
 * "Incorrect string value" — bukan sekadar memotong teks. Karena halaman
 * supplier ikut membaca kolom itu, kegagalan menyimpan membuat halaman tidak
 * bisa dibuka dan operator hanya melihat "Terjadi error ketika memuat halaman".
 */
class SupplierLogSanitizeTest extends TestCase
{
    use RefreshDatabase;

    protected function admin(): User
    {
        $u = User::factory()->create(['level' => 'admin']);
        $u->assignRole(Role::firstOrCreate(['name' => 'admin']));

        return $u;
    }

    protected function config(array $over = []): SupplierConfig
    {
        return SupplierConfig::create(array_merge([
            'code' => 'vip-reseller', 'name' => 'VIP Reseller',
            'provider_class' => VipResellerProvider::class,
            'is_active' => true, 'is_sandbox' => true, 'priority' => 0,
            'credentials' => ['api_id' => 'ID', 'api_key' => 'KEY'],
        ], $over));
    }

    protected function sanitize(string $text): string
    {
        $tester = app(SupplierConnectionTester::class);
        $m = new \ReflectionMethod($tester, 'sanitize');
        $m->setAccessible(true);

        return $m->invoke($tester, $text);
    }

    public function test_byte_utf8_rusak_dibuang(): void
    {
        $rusak = "Respons: \xC3\x28 \xFF\xFE \x80\x81";
        $this->assertFalse(mb_check_encoding($rusak, 'UTF-8'), 'teks uji harus rusak');

        $bersih = $this->sanitize($rusak);

        $this->assertTrue(mb_check_encoding($bersih, 'UTF-8'));
        $this->assertNotFalse(json_encode(['v' => $bersih]), 'JSON harus bisa di-encode');
    }

    /**
     * NUL dan byte kontrol lain LOLOS dari pemeriksaan UTF-8 (teks itu tetap
     * dianggap valid), jadi keduanya harus disaring terpisah.
     */
    public function test_nul_dan_byte_kontrol_dibuang(): void
    {
        $kotor = "awal\x00tengah\x01\x02\x1F akhir";

        $this->assertTrue(mb_check_encoding($kotor, 'UTF-8'), 'NUL tetap dianggap UTF-8 valid');

        $bersih = $this->sanitize($kotor);

        $this->assertStringNotContainsString("\x00", $bersih);
        $this->assertStringNotContainsString("\x01", $bersih);
        $this->assertStringNotContainsString("\x1F", $bersih);
        $this->assertStringContainsString('awal', $bersih);
        $this->assertStringContainsString('akhir', $bersih);
    }

    public function test_teks_normal_tidak_diubah(): void
    {
        $normal = 'Koneksi OK. Saldo: 1000. émoji 🎮 aman.';

        $this->assertSame($normal, $this->sanitize($normal));
    }

    /** Log rusak harus tetap bisa disimpan lewat jalur sungguhan. */
    public function test_log_rusak_tetap_tersimpan_dan_halaman_terbuka(): void
    {
        $c = $this->config();
        $this->actingAs($this->admin());

        $c->forceFill([
            'last_test_at' => now(),
            'last_test_ok' => false,
            'last_test_summary' => 'Uji',
            'last_test_log' => $this->sanitize("Respons mentah: \xC3\x28 \xFF\xFE \x00"),
        ])->save();

        $c->refresh();
        $this->assertTrue(mb_check_encoding((string) $c->last_test_log, 'UTF-8'));

        // Halaman yang membaca kolom itu harus tetap terbuka.
        $this->get('/admin/supplier-configs')->assertOk();
        $this->get('/admin/supplier-configs/'.$c->id.'/edit')->assertOk();

        Livewire::test(ListSupplierConfigs::class)
            ->mountAction('viewTestLog', arguments: ['record' => $c->getKey()])
            ->assertHasNoActionErrors();
    }

    /** Kegagalan kredensial menulis log lewat saveLog — jalur sanitasi asli. */
    public function test_tes_koneksi_gagal_menulis_log_yang_aman(): void
    {
        $c = $this->config(['credentials' => ['api_id' => '', 'api_key' => '']]);

        $hasil = app(SupplierConnectionTester::class)->test($c);
        $this->assertFalse($hasil['ok']);

        $c->refresh();
        $this->assertTrue(mb_check_encoding((string) $c->last_test_log, 'UTF-8'));
        $this->assertNotFalse(json_encode(['log' => $c->last_test_log]));
    }
}
