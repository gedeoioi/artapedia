<?php

namespace Tests\Feature;

use App\Jobs\SyncSupplierProducts;
use App\Models\Product;
use App\Models\SupplierConfig;
use App\Models\Transaction;
use App\Models\User;
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
            'provider_class' => \App\Suppliers\VipResellerProvider::class,
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

        Http::assertSent(fn ($req) => ($req->data()['filter_game'] ?? '') === 'Mobile Legends');
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
