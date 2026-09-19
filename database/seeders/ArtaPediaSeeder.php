<?php

namespace Database\Seeders;

use App\Models\PaymentGatewayConfig;
use App\Models\SupplierConfig;
use App\Models\User;
use App\Models\WaNotificationSetting;
use App\Payments\DuitkuGateway;
use App\Payments\IPaymuGateway;
use App\Payments\TripayGateway;
use App\Payments\XenditGateway;
use App\Suppliers\DigiflazzProvider;
use App\Suppliers\TokoVoucherProvider;
use App\Suppliers\VipResellerProvider;
use App\Support\AdminRoles;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class ArtaPediaSeeder extends Seeder
{
    public function run(): void
    {
        // Izin & peran panel dipusatkan di AdminRoles supaya pengecekan akses
        // resource dan seeder tidak bisa berbeda pendapat soal nama izin.
        foreach (AdminRoles::PERMISSIONS as $perm) {
            Permission::firstOrCreate(['name' => $perm]);
        }

        foreach (AdminRoles::ROLE_PERMISSIONS as $roleName => $permissions) {
            $role = Role::firstOrCreate(['name' => $roleName]);
            $role->syncPermissions($permissions);
        }

        $vip = Role::firstOrCreate(['name' => 'reseller-vip']);
        $biasa = Role::firstOrCreate(['name' => 'reseller-biasa']);

        $adminPassword = (string) config('artapedia.initial_admin.password');
        if (! app()->environment('production') || $adminPassword !== '') {
            $user = User::firstOrCreate(
                ['email' => config('artapedia.initial_admin.email')],
                ['name' => 'Admin', 'password' => $adminPassword ?: 'password', 'level' => 'admin', 'balance' => 0]
            );
            // Admin default adalah Super Admin: peran paling lengkap, dan satu-
            // satunya yang bisa mengelola user.
            $user->assignRole(Role::firstOrCreate(['name' => AdminRoles::SUPER_ADMIN]));
        }

        SupplierConfig::firstOrCreate(['code' => 'vip-reseller'], [
            'name' => 'VIP Reseller',
            'provider_class' => VipResellerProvider::class,
            'is_active' => false,
            'is_sandbox' => true,
            'priority' => 0,
            'credentials' => ['api_id' => '', 'api_key' => ''],
        ]);
        SupplierConfig::firstOrCreate(['code' => 'digiflazz'], [
            'name' => 'Digiflazz',
            'provider_class' => DigiflazzProvider::class,
            'is_active' => false,
            'is_sandbox' => true,
            'priority' => 1,
            'credentials' => ['username' => '', 'api_key' => '', 'webhook_secret' => ''],
        ]);
        SupplierConfig::firstOrCreate(['code' => 'toko-voucher'], [
            'name' => 'TokoVoucher',
            'provider_class' => TokoVoucherProvider::class,
            'is_active' => false,
            'is_sandbox' => true,
            'priority' => 2,
            'credentials' => ['member_code' => '', 'secret' => ''],
        ]);

        PaymentGatewayConfig::firstOrCreate(['code' => 'tripay'], [
            'name' => 'Tripay',
            'gateway_class' => TripayGateway::class,
            'is_active' => false,
            'is_sandbox' => true,
            'sort_order' => 0,
            'credentials' => ['api_key' => '', 'private_key' => '', 'merchant_code' => ''],
        ]);
        PaymentGatewayConfig::firstOrCreate(['code' => 'xendit'], [
            'name' => 'Xendit',
            'gateway_class' => XenditGateway::class,
            'is_active' => false,
            'is_sandbox' => true,
            'sort_order' => 1,
            'credentials' => ['secret_key' => '', 'callback_token' => ''],
        ]);
        PaymentGatewayConfig::firstOrCreate(['code' => 'duitku'], [
            'name' => 'Duitku',
            'gateway_class' => DuitkuGateway::class,
            'is_active' => false,
            'is_sandbox' => true,
            'sort_order' => 2,
            'credentials' => ['merchant_code' => '', 'api_key' => ''],
        ]);
        PaymentGatewayConfig::firstOrCreate(['code' => 'ipaymu'], [
            'name' => 'iPaymu',
            'gateway_class' => IPaymuGateway::class,
            'is_active' => false,
            'is_sandbox' => true,
            'sort_order' => 3,
            'credentials' => ['va' => '', 'secret' => ''],
        ]);

        WaNotificationSetting::firstOrCreate(['name' => 'trx_status'], [
            'is_active' => false,
            'template' => 'ArtaPedia: Invoice {invoice} status: {status}. Cek di {link}',
            'schedule' => 'on_event',
        ]);
        WaNotificationSetting::firstOrCreate(['name' => 'daily_recap'], [
            'is_active' => false,
            'template' => 'Rekap harian ArtaPedia.',
            'schedule' => 'daily',
        ]);
    }
}
