<?php

namespace Database\Seeders;

use App\Models\PaymentGatewayConfig;
use App\Models\SupplierConfig;
use App\Models\User;
use App\Models\WaNotificationSetting;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class ArtaPediaSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['manage users', 'manage products', 'manage suppliers', 'manage gateways', 'view reports', 'manual order'] as $perm) {
            Permission::firstOrCreate(['name' => $perm]);
        }

        $admin = Role::firstOrCreate(['name' => 'admin']);
        $vip = Role::firstOrCreate(['name' => 'reseller-vip']);
        $biasa = Role::firstOrCreate(['name' => 'reseller-biasa']);
        $admin->givePermissionTo(Permission::all());

        $user = User::firstOrCreate(
            ['email' => 'admin@artapedia.id'],
            ['name' => 'Admin', 'password' => 'password', 'level' => 'admin', 'balance' => 0]
        );
        $user->assignRole($admin);

        SupplierConfig::firstOrCreate(['code' => 'vip-reseller'], [
            'name' => 'VIP Reseller',
            'provider_class' => \App\Suppliers\VipResellerProvider::class,
            'is_active' => true,
            'is_sandbox' => true,
            'priority' => 0,
            'credentials' => ['api_id' => '', 'api_key' => ''],
        ]);
        SupplierConfig::firstOrCreate(['code' => 'digiflazz'], [
            'name' => 'Digiflazz',
            'provider_class' => \App\Suppliers\DigiflazzProvider::class,
            'is_active' => false,
            'is_sandbox' => true,
            'priority' => 1,
            'credentials' => ['username' => '', 'api_key' => '', 'webhook_secret' => ''],
        ]);
        SupplierConfig::firstOrCreate(['code' => 'toko-voucher'], [
            'name' => 'TokoVoucher',
            'provider_class' => \App\Suppliers\TokoVoucherProvider::class,
            'is_active' => false,
            'is_sandbox' => true,
            'priority' => 2,
            'credentials' => [],
        ]);

        PaymentGatewayConfig::firstOrCreate(['code' => 'xendit'], [
            'name' => 'Xendit',
            'gateway_class' => \App\Payments\XenditGateway::class,
            'is_active' => true,
            'is_sandbox' => true,
            'sort_order' => 0,
            'credentials' => ['secret_key' => '', 'callback_token' => ''],
        ]);
        PaymentGatewayConfig::firstOrCreate(['code' => 'duitku'], [
            'name' => 'Duitku',
            'gateway_class' => \App\Payments\DuitkuGateway::class,
            'is_active' => false,
            'is_sandbox' => true,
            'sort_order' => 1,
            'credentials' => ['merchant_code' => '', 'api_key' => ''],
        ]);
        PaymentGatewayConfig::firstOrCreate(['code' => 'ipaymu'], [
            'name' => 'iPaymu',
            'gateway_class' => \App\Payments\IPaymuGateway::class,
            'is_active' => false,
            'is_sandbox' => true,
            'sort_order' => 2,
            'credentials' => ['va' => '', 'secret' => ''],
        ]);

        WaNotificationSetting::firstOrCreate(['name' => 'trx_status'], [
            'is_active' => false,
            'template' => 'ArtaPedia: Invoice {invoice} status {status}.',
            'schedule' => 'on_event',
        ]);
        WaNotificationSetting::firstOrCreate(['name' => 'daily_recap'], [
            'is_active' => false,
            'template' => 'Rekap harian ArtaPedia.',
            'schedule' => 'daily',
        ]);
    }
}
