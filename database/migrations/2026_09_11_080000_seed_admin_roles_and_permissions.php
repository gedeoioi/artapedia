<?php

use App\Support\AdminRoles;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * Peran & izin panel dikirim lewat migration, bukan hanya seeder.
     *
     * Database yang sudah berjalan tidak pernah menjalankan ulang seeder, dan
     * begitu pengecekan izin dipasang di resource, instalasi lama akan terkunci
     * dari panelnya sendiri karena peran `admin`-nya belum punya izin baru.
     */
    public function up(): void
    {
        if (! Schema::hasTable('permissions') || ! Schema::hasTable('roles')) {
            return;
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (AdminRoles::PERMISSIONS as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        foreach (AdminRoles::ROLE_PERMISSIONS as $roleName => $permissions) {
            $role = Role::findOrCreate($roleName, 'web');
            $role->syncPermissions($permissions);
        }

        // Instalasi lama memakai peran `admin` sebagai satu-satunya peran staf.
        // Ia dipetakan ke Super Admin supaya tidak ada yang kehilangan akses,
        // lalu pemilik bisa menurunkannya sendiri lewat form user.
        $legacyAdmin = Role::where('name', 'admin')->first();
        if ($legacyAdmin) {
            $legacyAdmin->syncPermissions(AdminRoles::ROLE_PERMISSIONS[AdminRoles::SUPER_ADMIN]);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        if (! Schema::hasTable('permissions') || ! Schema::hasTable('roles')) {
            return;
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // Kembalikan peran `admin` ke izin versi lama.
        $legacyAdmin = Role::where('name', 'admin')->first();
        if ($legacyAdmin) {
            $legacyAdmin->syncPermissions([
                'manage users', 'manage products', 'manage suppliers',
                'manage gateways', 'view reports', 'manual order',
            ]);
        }

        foreach ([AdminRoles::SUPER_ADMIN, AdminRoles::OPERATOR] as $roleName) {
            Role::where('name', $roleName)->delete();
        }

        foreach ([
            AdminRoles::PERM_SETTINGS,
            AdminRoles::PERM_TRANSACTIONS,
            AdminRoles::PERM_BALANCE,
            AdminRoles::PERM_REVIEWS,
        ] as $permission) {
            Permission::where('name', $permission)->delete();
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
