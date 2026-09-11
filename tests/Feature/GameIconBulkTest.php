<?php

namespace Tests\Feature;

use App\Jobs\SyncSupplierProducts;
use App\Models\GameIcon;
use App\Models\Product;
use App\Models\SupplierConfig;
use App\Suppliers\VipResellerProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GameIconBulkTest extends TestCase
{
    use RefreshDatabase;

    public function test_ganti_icon_kategori_berlaku_ke_semua_produk_child(): void
    {
        $icon = GameIcon::create([
            'game_name' => 'Mobile Legends', 'slug' => 'mobile-legends',
            'icon_path' => 'game-icons/ml-v1.png', 'is_active' => true,
        ]);
        $p1 = Product::create([
            'supplier_code' => 'ML1', 'name' => 'ML 1', 'game' => 'Mobile Legends',
            'cost_basic' => 100, 'cost_premium' => 100, 'cost_special' => 100,
            'price_guest' => 120, 'price_biasa' => 115, 'price_vip' => 110,
            'is_active' => true, 'in_stock' => true, 'game_icon_id' => $icon->id,
        ]);
        $p2 = Product::create([
            'supplier_code' => 'ML2', 'name' => 'ML 2', 'game' => 'Mobile Legends',
            'cost_basic' => 200, 'cost_premium' => 200, 'cost_special' => 200,
            'price_guest' => 220, 'price_biasa' => 215, 'price_vip' => 210,
            'is_active' => true, 'in_stock' => true, 'game_icon_id' => $icon->id,
        ]);

        $this->assertStringEndsWith('ml-v1.png', $p1->fresh()->iconUrl());
        $this->assertStringEndsWith('ml-v1.png', $p2->fresh()->iconUrl());

        // Ganti 1 icon kategori -> semua child ikut berubah (tanpa edit produk satu per satu).
        $icon->update(['icon_path' => 'game-icons/ml-v2.png']);

        $this->assertStringEndsWith('ml-v2.png', $p1->fresh()->iconUrl());
        $this->assertStringEndsWith('ml-v2.png', $p2->fresh()->iconUrl());
    }

    public function test_override_produk_tidak_tertimpa_icon_kategori(): void
    {
        $icon = GameIcon::create([
            'game_name' => 'Mobile Legends', 'slug' => 'ml',
            'icon_path' => 'game-icons/ml.png', 'is_active' => true,
        ]);
        $p = Product::create([
            'supplier_code' => 'MLX', 'name' => 'ML X', 'game' => 'Mobile Legends',
            'cost_basic' => 100, 'cost_premium' => 100, 'cost_special' => 100,
            'price_guest' => 120, 'price_biasa' => 115, 'price_vip' => 110,
            'is_active' => true, 'in_stock' => true,
            'image_path' => 'products/khusus.png', 'game_icon_id' => $icon->id,
        ]);

        $this->assertStringEndsWith('products/khusus.png', $p->fresh()->iconUrl());
    }

    public function test_produk_lama_tanpa_relasi_tetap_dapat_icon_by_nama(): void
    {
        GameIcon::create([
            'game_name' => 'Free Fire', 'slug' => 'ff',
            'icon_path' => 'game-icons/ff.png', 'is_active' => true,
        ]);
        $p = Product::create([
            'supplier_code' => 'FF5', 'name' => 'FF 5', 'game' => 'Free Fire',
            'cost_basic' => 100, 'cost_premium' => 100, 'cost_special' => 100,
            'price_guest' => 120, 'price_biasa' => 115, 'price_vip' => 110,
            'is_active' => true, 'in_stock' => true, 'game_icon_id' => null,
        ]);

        $this->assertStringEndsWith('game-icons/ff.png', $p->fresh()->iconUrl());
    }

    public function test_hapus_kategori_menonaktifkan_produk_child_dan_menghilangkannya_dari_beranda(): void
    {
        $category = GameIcon::create([
            'game_name' => 'Mobile Legends',
            'slug' => 'mobile-legends',
            'is_active' => true,
        ]);
        $linked = Product::create([
            'supplier_code' => 'ML-DELETE-1', 'name' => 'ML 5', 'game' => 'Mobile Legends',
            'cost_basic' => 100, 'cost_premium' => 100, 'cost_special' => 100,
            'price_guest' => 120, 'price_biasa' => 115, 'price_vip' => 110,
            'is_active' => true, 'in_stock' => true, 'game_icon_id' => $category->id,
        ]);
        $legacy = Product::create([
            'supplier_code' => 'ML-DELETE-2', 'name' => 'ML 12', 'game' => 'mobile legends',
            'cost_basic' => 200, 'cost_premium' => 200, 'cost_special' => 200,
            'price_guest' => 220, 'price_biasa' => 215, 'price_vip' => 210,
            'is_active' => true, 'in_stock' => true,
        ]);

        $this->get('/')->assertOk()->assertSee('Mobile Legends');

        $category->delete();

        $this->assertDatabaseHas('products', ['id' => $linked->id, 'is_active' => false, 'in_stock' => false]);
        $this->assertDatabaseHas('products', ['id' => $legacy->id, 'is_active' => false, 'in_stock' => false]);
        $this->get('/')->assertOk()->assertDontSee('Mobile Legends');
    }

    public function test_sync_otomatis_hubungkan_icon_by_nama(): void
    {
        GameIcon::create([
            'game_name' => 'Mobile Legends', 'slug' => 'ml',
            'icon_path' => 'game-icons/ml.png', 'is_active' => true,
        ]);
        $s = SupplierConfig::create([
            'code' => 'vip-reseller', 'name' => 'VIP',
            'provider_class' => VipResellerProvider::class,
            'is_active' => true, 'is_sandbox' => true, 'priority' => 0,
            'credentials' => ['api_id' => 'ID', 'api_key' => 'KEY'],
        ]);

        Http::fake(['vip-reseller.co.id/*' => Http::response(['result' => true, 'data' => [
            ['code' => 'ML1', 'game' => 'Mobile Legends', 'name' => 'ML 1',
                'price' => ['basic' => 1000, 'premium' => 900, 'special' => 800], 'status' => 'available'],
        ]], 200)]);

        (new SyncSupplierProducts($s->id, []))->handle();

        $p = Product::first();
        $this->assertEquals('game-icons/ml.png', $p->gameIcon->icon_path);
        $this->assertStringEndsWith('game-icons/ml.png', $p->fresh()->iconUrl());
    }
}
