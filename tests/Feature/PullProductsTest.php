<?php

namespace Tests\Feature;

use App\Filament\Pages\PullProducts;
use App\Jobs\SyncSupplierProducts;
use App\Models\Product;
use App\Models\SupplierConfig;
use App\Models\Transaction;
use App\Models\User;
use App\Suppliers\VipResellerProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PullProductsTest extends TestCase
{
    use RefreshDatabase;

    protected function makeSupplier(): SupplierConfig
    {
        return SupplierConfig::create([
            'code' => 'vip-reseller', 'name' => 'VIP',
            'provider_class' => VipResellerProvider::class,
            'is_active' => true, 'is_sandbox' => true, 'priority' => 0,
            'credentials' => ['api_id' => 'ID', 'api_key' => 'KEY'],
        ]);
    }

    public function test_sync_filter_game_hanya_menyimpan_kategori_itu(): void
    {
        $s = $this->makeSupplier();

        Http::fake(['vip-reseller.co.id/api/game-feature' => Http::response(['result' => true, 'data' => [
            ['code' => 'ML1', 'game' => 'Mobile Legends', 'name' => 'ML 1',
                'price' => ['basic' => 1000, 'premium' => 900, 'special' => 800], 'status' => 'available'],
        ]], 200)]);

        (new SyncSupplierProducts($s->id, ['game' => 'Mobile Legends', 'status' => 'available']))->handle();

        $this->assertEquals(1, Product::where('supplier_config_id', $s->id)->count());
        $p = Product::first();
        $this->assertEquals('Mobile Legends', $p->game);
        $this->assertEquals(1000, $p->cost_basic);
        $this->assertEquals(900, $p->cost_premium);
        $this->assertEquals(800, $p->cost_special);
        $this->assertEquals(1050, $p->price_guest);
        $this->assertEquals(945, $p->price_biasa);
        $this->assertEquals(840, $p->price_vip);
        $this->assertSame(Product::TYPE_GAME, $p->product_type);
        $this->assertTrue($p->supportsMultipleQuantity());

        Http::assertSent(fn ($req) => ($req->data()['filter_game'] ?? '') === 'Mobile Legends');
    }

    public function test_sync_gagal_mengembalikan_pesan_asli(): void
    {
        $s = $this->makeSupplier();

        Http::fake(['vip-reseller.co.id/*' => Http::response(['result' => false, 'message' => 'Invalid key'], 200)]);

        $result = (new SyncSupplierProducts($s->id, []))->handle();

        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('Invalid key', $result['message']);
        $this->assertEquals(0, Product::where('supplier_config_id', $s->id)->count());
    }

    public function test_penarikan_paket_internet_tidak_menyimpan_pulsa_reguler(): void
    {
        $supplier = $this->makeSupplier();
        Http::fake(['vip-reseller.co.id/api/prepaid' => Http::response([
            'result' => true,
            'data' => [
                ['code' => 'TSEL-PULSA-10', 'brand' => 'Telkomsel', 'name' => 'Pulsa Reguler 10.000', 'category' => 'Pulsa', 'type' => 'pulsa', 'price' => 10500, 'status' => 'available'],
                ['code' => 'TSEL-DATA-10', 'brand' => 'Telkomsel', 'name' => 'Paket Internet 10 GB', 'category' => 'Paket Data', 'type' => 'data', 'price' => 25000, 'status' => 'available'],
            ],
        ], 200)]);

        $result = (new SyncSupplierProducts($supplier->id, [
            'channel' => 'prepaid',
            'cmd' => 'prepaid',
            'desired_product_type' => Product::TYPE_DATA,
        ]))->handle();

        $this->assertTrue($result['ok']);
        $this->assertSame(1, $result['synced']);
        $this->assertDatabaseHas('products', [
            'supplier_code' => 'TSEL-DATA-10',
            'product_type' => Product::TYPE_DATA,
        ]);
        $this->assertDatabaseMissing('products', ['supplier_code' => 'TSEL-PULSA-10']);
    }

    public function test_admin_dapat_memilih_pulsa_reguler_dan_paket_internet_secara_terpisah(): void
    {
        $admin = User::factory()->create(['level' => 'admin']);

        $this->actingAs($admin)
            ->get(PullProducts::getUrl())
            ->assertOk()
            ->assertSee('Pulsa Reguler')
            ->assertSee('Paket Internet / Data');
    }

    public function test_hapus_proteksi_produk_bertransaksi(): void
    {
        $s = $this->makeSupplier();
        $used = Product::create([
            'supplier_config_id' => $s->id, 'supplier_code' => 'USED',
            'name' => 'Used', 'game' => 'ML',
            'cost_basic' => 100, 'cost_premium' => 100, 'cost_special' => 100,
            'price_guest' => 120, 'price_biasa' => 115, 'price_vip' => 110,
            'is_active' => true, 'in_stock' => true,
        ]);
        $free = Product::create([
            'supplier_config_id' => $s->id, 'supplier_code' => 'FREE',
            'name' => 'Free', 'game' => 'ML',
            'cost_basic' => 100, 'cost_premium' => 100, 'cost_special' => 100,
            'price_guest' => 120, 'price_biasa' => 115, 'price_vip' => 110,
            'is_active' => true, 'in_stock' => true,
        ]);
        $user = User::factory()->create();
        Transaction::create([
            'invoice_code' => 'INV-PROT-1', 'user_id' => $user->id, 'product_id' => $used->id,
            'payment_gateway_code' => 'balance', 'target_user_id' => '1',
            'quantity' => 1, 'cost_price' => 100, 'sell_price' => 120,
            'admin_fee' => 0, 'gateway_fee' => 0, 'total_amount' => 120, 'profit' => 20,
            'payment_method' => 'balance', 'status' => 'success',
        ]);

        // Simulasi logika tombol Hapus Produk (cakupan supplier, tanpa filter game).
        $ids = Product::where('supplier_config_id', $s->id)->pluck('id');
        $deleted = Product::whereIn('id', $ids)->whereNotIn('id', function ($q) {
            $q->select('product_id')->from('transactions')->whereNotNull('product_id');
        })->delete();
        $deactivated = Product::where('supplier_config_id', $s->id)->whereIn('id', function ($q) {
            $q->select('product_id')->from('transactions')->whereNotNull('product_id');
        })->update(['is_active' => false, 'in_stock' => false]);

        $this->assertEquals(1, $deleted);
        $this->assertEquals(1, $deactivated);
        $this->assertNull(Product::find($free->id));
        $this->assertFalse((bool) $used->fresh()->is_active);
        $this->assertEquals(1, Transaction::count());
    }
}
