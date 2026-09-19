<?php

namespace Tests\Feature;

use App\Filament\Pages\SiteSettings;
use App\Models\ManualTopup;
use App\Models\SiteSetting;
use App\Models\User;
use App\Services\ManualTopupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Panel admin harus benar-benar menggerakkan storefront.
 *
 * Panel yang menyimpan statusnya sendiri dan menampilkannya kembali akan lulus
 * self-check sementara storefront mengabaikannya — hanya assertion lintas
 * halaman yang menangkap itu.
 */
class SiteSettingsManualTopupTest extends TestCase
{
    use RefreshDatabase;

    protected function admin(): User
    {
        $admin = User::factory()->create(['level' => 'admin']);
        $admin->assignRole(Role::firstOrCreate(['name' => 'admin']));

        return $admin;
    }

    public function test_layar_pengaturan_website_render(): void
    {
        $this->actingAs($this->admin())->get('/admin/site-settings')->assertOk();
    }

    public function test_simpan_dari_admin_menggerakkan_halaman_member(): void
    {
        $this->actingAs($this->admin());

        Livewire::test(SiteSettings::class)
            ->fillForm([
                'manual_topup_enabled' => true,
                'manual_topup_min' => 25000,
                'manual_topup_banks' => [
                    ['name' => 'BCA', 'account_number' => '111222333', 'account_name' => 'PT Arta Pedia'],
                    ['name' => 'Mandiri', 'account_number' => '444555666', 'account_name' => 'PT Arta Pedia'],
                ],
            ])
            ->callAction('save')
            ->assertHasNoActionErrors();

        // 1. Tersimpan sebagai string JSON, bukan array mentah.
        $stored = SiteSetting::where('key', 'manual_topup_banks')->value('value');
        $this->assertIsString($stored);
        $this->assertIsArray(json_decode($stored, true));

        // 2. Service membacanya kembali dengan benar.
        $banks = app(ManualTopupService::class)->banks();
        $this->assertCount(2, $banks);
        $this->assertSame('BCA', $banks[0]['name']);
        $this->assertSame('111222333', $banks[0]['account_number']);
        $this->assertSame(25000, app(ManualTopupService::class)->minimumAmount());
        $this->assertTrue(app(ManualTopupService::class)->isEnabled());

        // 3. Storefront benar-benar menampilkan rekening itu (assertion lintas halaman).
        $member = User::factory()->create(['level' => 'member']);
        $this->actingAs($member)
            ->get('/member/topup-manual')
            ->assertOk()
            ->assertSee('BCA')
            ->assertSee('111222333')
            ->assertSee('Mandiri')
            ->assertSee('444555666')
            ->assertSee('PT Arta Pedia')
            ->assertSee('25.000');
    }

    public function test_mematikan_toggle_dari_admin_menonaktifkan_halaman_member(): void
    {
        $this->actingAs($this->admin());

        Livewire::test(SiteSettings::class)
            ->fillForm([
                'manual_topup_enabled' => false,
                'manual_topup_banks' => [
                    ['name' => 'BCA', 'account_number' => '111222333', 'account_name' => 'PT Arta Pedia'],
                ],
            ])
            ->callAction('save')
            ->assertHasNoActionErrors();

        $member = User::factory()->create(['level' => 'member']);

        $this->actingAs($member)
            ->get('/member/topup-manual')
            ->assertOk()
            ->assertSee('sedang tidak tersedia')
            ->assertDontSee('111222333');
    }

    public function test_rekening_diubah_di_admin_langsung_terlihat_di_member(): void
    {
        $this->actingAs($this->admin());

        Livewire::test(SiteSettings::class)
            ->fillForm([
                'manual_topup_enabled' => true,
                'manual_topup_banks' => [
                    ['name' => 'BCA', 'account_number' => 'LAMA-123', 'account_name' => 'Arta'],
                ],
            ])
            ->callAction('save');

        $member = User::factory()->create(['level' => 'member']);
        $this->actingAs($member)->get('/member/topup-manual')->assertSee('LAMA-123');

        // Ganti rekening dari admin, lalu baca ulang halaman member.
        $this->actingAs($this->admin());
        Livewire::test(SiteSettings::class)
            ->fillForm([
                'manual_topup_enabled' => true,
                'manual_topup_banks' => [
                    ['name' => 'BNI', 'account_number' => 'BARU-999', 'account_name' => 'Arta'],
                ],
            ])
            ->callAction('save');

        $this->actingAs($member)
            ->get('/member/topup-manual')
            ->assertOk()
            ->assertSee('BARU-999')
            ->assertDontSee('LAMA-123');
    }

    public function test_pengajuan_manual_tetap_bisa_disetujui_setelah_rekening_diubah(): void
    {
        SiteSetting::setMany([
            'manual_topup_enabled' => '1',
            'manual_topup_min' => '10000',
            'manual_topup_banks' => [['name' => 'BCA', 'account_number' => '111', 'account_name' => 'Arta']],
        ]);

        $member = User::factory()->create(['level' => 'member', 'balance' => 0]);
        $admin = $this->admin();

        $topup = ManualTopup::create([
            'code' => 'MT-TEST-1', 'user_id' => $member->id, 'amount' => 30000,
            'bank_name' => 'BCA', 'bank_account_number' => '111', 'bank_account_name' => 'Arta',
            'proof_path' => 'manual-topup/bukti.jpg', 'status' => ManualTopup::STATUS_PENDING,
        ]);

        app(ManualTopupService::class)->approve($topup, $admin);

        $this->assertSame(30000, $member->fresh()->balance);
    }
}
