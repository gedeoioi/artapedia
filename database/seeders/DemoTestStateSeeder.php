<?php

namespace Database\Seeders;

use App\Models\BalanceMutation;
use App\Models\PaymentGatewayConfig;
use App\Models\SiteSetting;
use App\Models\SupplierConfig;
use App\Models\User;
use App\Payments\TripayGateway;
use App\Services\BalanceService;
use App\Suppliers\VipResellerProvider;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

/**
 * Menyiapkan state untuk pengujian manual di lokal.
 *
 * Idempotent: aman dijalankan berkali-kali tanpa menggandakan saldo atau
 * membuat data duplikat. Jalankan dengan:
 *   php artisan db:seed --class=DemoTestStateSeeder
 *
 * JANGAN dijalankan di produksi.
 */
class DemoTestStateSeeder extends Seeder
{
    public const DEMO_EMAIL = 'member@artapedia.id';

    public const DEMO_PASSWORD = 'password';

    public const DEMO_BALANCE = 100000;

    private const BALANCE_NOTE = 'Saldo demo untuk pengujian lokal';

    public function run(): void
    {
        if (app()->environment('production')) {
            $this->command?->error('DemoTestStateSeeder tidak boleh dijalankan di produksi.');

            return;
        }

        $this->seedMember();
        $this->seedManualTopupSettings();
        $this->activateGateways();
        $this->activateSupplier();

        $this->command?->newLine();
        $this->command?->info('State uji siap. Lihat ringkasan di atas.');
    }

    private function seedMember(): void
    {
        $member = User::firstOrCreate(
            ['email' => self::DEMO_EMAIL],
            [
                'name' => 'Member Demo',
                'password' => Hash::make(self::DEMO_PASSWORD),
                'level' => 'member',
                'status' => 'active',
                'phone' => '081234567890',
                'whatsapp' => '081234567890',
                'balance' => 0,
            ],
        );

        // Saldo WAJIB lewat ledger. Kalau ditulis langsung ke kolom `balance`,
        // balance:reconcile langsung melaporkan selisih dan invarian
        // "setiap perubahan saldo punya baris ledger" rusak.
        $alreadyCredited = BalanceMutation::where('user_id', $member->id)
            ->where('description', self::BALANCE_NOTE)
            ->exists();

        if ($alreadyCredited) {
            $this->command?->line('Member demo: sudah ada (saldo tidak ditambah lagi).');
        } else {
            app(BalanceService::class)->credit(
                $member,
                self::DEMO_BALANCE,
                BalanceMutation::TYPE_ADJUST,
                self::BALANCE_NOTE,
            );

            $this->command?->line('Member demo: dibuat + saldo Rp '.number_format(self::DEMO_BALANCE, 0, ',', '.').' via ledger.');
        }

        if ($member->getRoleNames()->isEmpty()) {
            $member->assignRole(Role::firstOrCreate(['name' => 'reseller-biasa']));
        }
    }

    private function seedManualTopupSettings(): void
    {
        // Nomor rekening sengaja dibuat jelas-jelas palsu. Rekening yang terlihat
        // asli berisiko benar-benar ditransfer orang.
        SiteSetting::setMany([
            'manual_topup_enabled' => '1',
            'manual_topup_min' => '10000',
            'manual_topup_banks' => [
                [
                    'name' => 'BCA (CONTOH)',
                    'account_number' => '0000000001',
                    'account_name' => 'CONTOH - JANGAN TRANSFER',
                ],
                [
                    'name' => 'Mandiri (CONTOH)',
                    'account_number' => '0000000002',
                    'account_name' => 'CONTOH - JANGAN TRANSFER',
                ],
            ],
        ]);

        $this->command?->line('Topup manual: aktif, minimum Rp 10.000, 2 rekening contoh.');
    }

    private function activateGateways(): void
    {
        // Tripay diaktifkan sebagai default (sort_order 0). Kredensial dibiarkan
        // kosong: channel & UI checkout bisa diuji, tetapi pembuatan pembayaran
        // akan gagal dengan pesan jelas sampai API Key/Private Key/Merchant Code
        // asli dari akun sandbox Tripay diisi lewat /admin/payment-gateway-configs.
        PaymentGatewayConfig::updateOrCreate(['code' => 'tripay'], [
            'name' => 'Tripay',
            'gateway_class' => TripayGateway::class,
            'is_active' => true,
            'is_sandbox' => true,
            'sort_order' => 0,
        ]);

        $this->command?->line('Gateway: Tripay aktif (mode sandbox, kredensial masih kosong).');
    }

    private function activateSupplier(): void
    {
        // Supplier aktif supaya alur order benar-benar berjalan sampai pemanggilan
        // HTTP. Tanpa kredensial asli, order akan ditolak supplier dan otomatis
        // di-refund — itu justru menguji jalur refund, bukan crash.
        SupplierConfig::updateOrCreate(['code' => 'vip-reseller'], [
            'name' => 'VIP Reseller',
            'provider_class' => VipResellerProvider::class,
            'is_active' => true,
            'is_sandbox' => true,
            'priority' => 0,
        ]);

        $this->command?->line('Supplier: VIP Reseller aktif (kredensial masih kosong).');
    }
}
