<?php

namespace Tests\Feature;

use App\Filament\Resources\ManualTopups\Pages\ListManualTopups;
use App\Filament\Resources\Ratings\Pages\ListRatings;
use App\Filament\Resources\Transactions\Pages\ListTransactions;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Models\AuditLog;
use App\Models\BalanceMutation;
use App\Models\User;
use App\Support\AdminRoles;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminRolePermissionTest extends TestCase
{
    use RefreshDatabase;

    protected function seedRoles(): void
    {
        foreach (AdminRoles::PERMISSIONS as $perm) {
            Permission::firstOrCreate(['name' => $perm]);
        }

        foreach (AdminRoles::ROLE_PERMISSIONS as $roleName => $permissions) {
            Role::firstOrCreate(['name' => $roleName])->syncPermissions($permissions);
        }
    }

    protected function staff(string $role): User
    {
        $this->seedRoles();

        $user = User::factory()->create(['level' => 'admin', 'status' => 'active']);
        $user->assignRole($role);

        return $user;
    }

    public function test_super_admin_bisa_membuka_semua_layar_admin(): void
    {
        $super = $this->staff(AdminRoles::SUPER_ADMIN);

        foreach ([
            '/admin', '/admin/users', '/admin/products', '/admin/transactions',
            '/admin/supplier-configs', '/admin/payment-gateway-configs',
            '/admin/cron-settings', '/admin/wa-notification-settings',
            '/admin/audit-logs', '/admin/manual-topups', '/admin/ratings',
            '/admin/site-settings', '/admin/profit-settings',
        ] as $path) {
            $this->actingAs($super)->get($path)->assertOk();
        }
    }

    /**
     * Operator menangani transaksi dan review, tapi tidak boleh menyentuh
     * konfigurasi, katalog, atau data user.
     */
    public function test_operator_tidak_bisa_membuka_konfigurasi_dan_user(): void
    {
        $operator = $this->staff(AdminRoles::OPERATOR);

        foreach ([
            '/admin/users',
            '/admin/products',
            '/admin/payment-gateway-configs',
            '/admin/supplier-configs',
            '/admin/cron-settings',
            '/admin/wa-notification-settings',
            '/admin/site-settings',
            '/admin/profit-settings',
            '/admin/audit-logs',
        ] as $path) {
            $this->actingAs($operator)->get($path)->assertForbidden();
        }
    }

    public function test_operator_tetap_bisa_mengelola_transaksi_dan_review(): void
    {
        $operator = $this->staff(AdminRoles::OPERATOR);

        $this->actingAs($operator)->get('/admin/transactions')->assertOk();
        $this->actingAs($operator)->get('/admin/manual-topups')->assertOk();
        $this->actingAs($operator)->get('/admin/ratings')->assertOk();

        Livewire::test(ListTransactions::class)->assertOk();
        Livewire::test(ListManualTopups::class)->assertOk();
        Livewire::test(ListRatings::class)->assertOk();
    }

    public function test_operator_tidak_bisa_melihat_laporan_keuangan(): void
    {
        $operator = $this->staff(AdminRoles::OPERATOR);

        $this->assertFalse($operator->hasAdminPermission(AdminRoles::PERM_REPORTS));
        $this->actingAs($operator)->get('/admin/reports')->assertForbidden();
        $this->actingAs($operator)->get('/admin/reports/print')->assertForbidden();
    }

    public function test_admin_tidak_bisa_mengelola_user(): void
    {
        $admin = $this->staff(AdminRoles::ADMIN);

        $this->actingAs($admin)->get('/admin/users')->assertForbidden();
        $this->actingAs($admin)->get('/admin/site-settings')->assertOk();
        $this->actingAs($admin)->get('/admin/products')->assertOk();
    }

    public function test_bukan_staf_tidak_bisa_masuk_panel(): void
    {
        $member = User::factory()->create(['level' => 'member', 'status' => 'active']);

        $this->actingAs($member)->get('/admin')->assertForbidden();
        $this->actingAs($member)->get('/admin/transactions')->assertForbidden();
        $this->actingAs($member)->get('/admin/ratings')->assertForbidden();
    }

    public function test_user_suspend_tidak_bisa_masuk_panel_walau_punya_peran(): void
    {
        $this->seedRoles();

        $user = User::factory()->create(['level' => 'admin', 'status' => 'suspended']);
        $user->assignRole(AdminRoles::SUPER_ADMIN);

        $this->assertFalse($user->canAccessPanel(Filament::getPanel('admin')));
    }

    public function test_operator_tidak_punya_izin_sesuaikan_saldo(): void
    {
        $operator = $this->staff(AdminRoles::OPERATOR);
        $admin = $this->staff(AdminRoles::ADMIN);
        $super = $this->staff(AdminRoles::SUPER_ADMIN);

        $this->assertFalse($operator->hasAdminPermission(AdminRoles::PERM_BALANCE));
        $this->assertTrue($admin->hasAdminPermission(AdminRoles::PERM_BALANCE));
        $this->assertTrue($super->hasAdminPermission(AdminRoles::PERM_BALANCE));
    }

    public function test_super_admin_selalu_boleh_walau_peran_tidak_punya_izin(): void
    {
        $this->seedRoles();

        $super = User::factory()->create(['level' => 'admin']);
        $super->assignRole(AdminRoles::SUPER_ADMIN);
        // Cabut semua izin langsung dari peran; Super Admin tetap harus lolos.
        Role::where('name', AdminRoles::SUPER_ADMIN)->first()->syncPermissions([]);

        foreach (AdminRoles::PERMISSIONS as $permission) {
            $this->assertTrue($super->hasAdminPermission($permission));
        }
    }

    /**
     * Penyesuaian saldo manual wajib meninggalkan jejak: satu baris ledger
     * (tidak pernah mengedit entri lama) plus audit trail berisi alasan.
     */
    public function test_penyesuaian_saldo_mencatat_ledger_dan_audit_trail(): void
    {
        $super = $this->staff(AdminRoles::SUPER_ADMIN);
        $target = User::factory()->create(['balance' => 5000, 'level' => 'member']);

        $this->actingAs($super);

        Livewire::test(ListUsers::class)
            ->callTableAction('adjustBalance', $target, data: [
                'direction' => 'credit',
                'amount' => 25000,
                'reason' => 'Bonus kompensasi gangguan supplier',
            ])
            ->assertHasNoTableActionErrors();

        $target->refresh();
        $this->assertSame(30000, $target->balance);

        $mutation = BalanceMutation::where('user_id', $target->id)->latest('id')->first();
        $this->assertNotNull($mutation);
        $this->assertSame(BalanceMutation::TYPE_ADJUST, $mutation->type);
        $this->assertSame(25000, $mutation->amount);
        $this->assertSame(5000, $mutation->balance_before);
        $this->assertSame(30000, $mutation->balance_after);
        $this->assertSame('Bonus kompensasi gangguan supplier', $mutation->description);
        $this->assertSame($super->id, $mutation->created_by);

        $audit = AuditLog::where('action', 'balance.adjust')->latest('id')->first();
        $this->assertNotNull($audit, 'Penyesuaian saldo wajib tercatat di audit trail.');
        $this->assertSame($super->id, $audit->user_id);
        $this->assertSame(5000, $audit->old_values['balance']);
        $this->assertSame(30000, $audit->new_values['balance']);

        // Ledger append-only: entri lama tidak diubah.
        $this->assertSame(1, BalanceMutation::where('user_id', $target->id)->count());
    }

    public function test_penyesuaian_saldo_negatif_ditolak_dan_tidak_mengubah_apa_pun(): void
    {
        $super = $this->staff(AdminRoles::SUPER_ADMIN);
        $target = User::factory()->create(['balance' => 5000, 'level' => 'member']);

        $this->actingAs($super);

        Livewire::test(ListUsers::class)
            ->callTableAction('adjustBalance', $target, data: [
                'direction' => 'debit',
                'amount' => 999999,
                'reason' => 'Salah input nominal',
            ]);

        $this->assertSame(5000, $target->fresh()->balance, 'Saldo tidak boleh jadi negatif.');
        $this->assertSame(0, BalanceMutation::where('user_id', $target->id)->count());
    }

    public function test_form_user_menyediakan_pemilihan_peran(): void
    {
        $super = $this->staff(AdminRoles::SUPER_ADMIN);

        $this->actingAs($super)->get('/admin/users/create')->assertOk();
    }
}
