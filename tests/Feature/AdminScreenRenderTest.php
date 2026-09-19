<?php

namespace Tests\Feature;

use App\Filament\Resources\ManualTopups\Pages\ListManualTopups;
use App\Filament\Resources\Ratings\Pages\ListRatings;
use App\Models\ManualTopup;
use App\Models\Product;
use App\Models\Rating;
use App\Models\SupplierConfig;
use App\Models\Transaction;
use App\Models\User;
use App\Suppliers\VipResellerProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Setiap layar admin yang ditambahkan/disentuh harus benar-benar dirender.
 * Komponen yang hilang, method yang berubah antar versi framework, atau view
 * yang tidak ada hanya meledak saat halaman dibuka — route:list, autoload, dan
 * lint semuanya tetap hijau sementara layarnya 500.
 *
 * Catatan: baris tabel Filament 5 dihidrasi lewat Livewire, jadi HTTP GET saja
 * hanya membuktikan halamannya tidak 500 — isinya harus diperiksa lewat
 * Livewire::test + assertCanSeeTableRecords.
 */
class AdminScreenRenderTest extends TestCase
{
    use RefreshDatabase;

    protected function admin(): User
    {
        $admin = User::factory()->create(['level' => 'admin']);
        $admin->assignRole(Role::firstOrCreate(['name' => 'admin']));

        return $admin;
    }

    protected function seedTransaction(User $member): Transaction
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

        return Transaction::create([
            'invoice_code' => 'INV-ADM-RATE-1', 'user_id' => $member->id, 'product_id' => $product->id,
            'payment_gateway_code' => 'balance', 'target_user_id' => '123',
            'quantity' => 1, 'cost_price' => 10000, 'sell_price' => 12000,
            'admin_fee' => 0, 'gateway_fee' => 0, 'total_amount' => 12000, 'profit' => 2000,
            'payment_method' => 'balance', 'status' => Transaction::STATUS_SUCCESS,
        ]);
    }

    public function test_dashboard_admin_render(): void
    {
        $this->actingAs($this->admin())->get('/admin')->assertOk();
    }

    public function test_semua_layar_admin_utama_render(): void
    {
        $admin = $this->admin();

        $paths = [
            '/admin/users',
            '/admin/products',
            '/admin/transactions',
            '/admin/supplier-configs',
            '/admin/payment-gateway-configs',
            '/admin/game-icons',
            '/admin/banners',
            '/admin/audit-logs',
            '/admin/cron-settings',
            '/admin/wa-notification-settings',
            '/admin/manual-topups',
            '/admin/ratings',
        ];

        foreach ($paths as $path) {
            $this->actingAs($admin)->get($path)->assertOk();
        }
    }

    public function test_layar_topup_manual_menampilkan_pengajuan_pending(): void
    {
        $admin = $this->admin();
        $member = User::factory()->create(['name' => 'Budi Member', 'level' => 'member']);

        $topup = ManualTopup::create([
            'code' => 'MT-20260101-ABC123',
            'user_id' => $member->id,
            'amount' => 75000,
            'bank_name' => 'BCA',
            'bank_account_name' => 'ArtaPedia',
            'bank_account_number' => '1234567890',
            'sender_name' => 'Budi',
            'proof_path' => 'manual-topup/bukti.jpg',
            'status' => ManualTopup::STATUS_PENDING,
        ]);

        $this->actingAs($admin);

        Livewire::test(ListManualTopups::class)
            ->assertOk()
            ->assertCanSeeTableRecords([$topup])
            ->assertCountTableRecords(1)
            ->assertTableColumnStateSet('code', 'MT-20260101-ABC123', $topup->getKey())
            ->assertTableColumnStateSet('amount', 75000, $topup->getKey())
            ->assertTableColumnStateSet('bank_name', 'BCA', $topup->getKey());
    }

    public function test_layar_moderasi_rating_menampilkan_rating_pending(): void
    {
        $admin = $this->admin();
        $member = User::factory()->create(['level' => 'member']);
        $trx = $this->seedTransaction($member);

        $rating = Rating::create([
            'transaction_id' => $trx->id, 'user_id' => $member->id,
            'stars' => 3, 'comment' => 'Lumayan tapi agak lambat',
            'status' => Rating::STATUS_PENDING,
        ]);

        $this->actingAs($admin);

        Livewire::test(ListRatings::class)
            ->assertOk()
            ->assertCanSeeTableRecords([$rating])
            ->assertCountTableRecords(1)
            ->assertTableColumnFormattedStateSet('comment', 'Lumayan tapi agak lambat', $rating->getKey())
            ->assertTableColumnStateSet('transaction.invoice_code', 'INV-ADM-RATE-1', $rating->getKey());
    }

    public function test_bukan_admin_tidak_bisa_membuka_layar_admin(): void
    {
        $member = User::factory()->create(['level' => 'member']);

        $this->actingAs($member)->get('/admin/manual-topups')->assertForbidden();
        $this->actingAs($member)->get('/admin/ratings')->assertForbidden();
    }
}
