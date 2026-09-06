<?php

namespace Tests\Feature;

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductVisibilityTest extends TestCase
{
    use RefreshDatabase;

    protected function makeProduct(array $over = []): Product
    {
        return Product::create(array_merge([
            'supplier_code' => 'X'.uniqid(), 'name' => 'P', 'game' => 'ML',
            'cost_basic' => 100, 'cost_premium' => 100, 'cost_special' => 100,
            'price_guest' => 120, 'price_biasa' => 115, 'price_vip' => 110,
            'is_active' => true, 'in_stock' => true,
        ], $over));
    }

    public function test_produk_kosong_tidak_masuk_scope_available(): void
    {
        $this->makeProduct(['supplier_code' => 'A']);
        $this->makeProduct(['supplier_code' => 'B', 'in_stock' => false]);
        $this->makeProduct(['supplier_code' => 'C', 'is_active' => false]);

        $this->assertEquals(['A'], Product::available()->pluck('supplier_code')->all());
    }

    public function test_beranda_tidak_menampilkan_produk_kosong(): void
    {
        $this->makeProduct(['supplier_code' => 'A', 'name' => 'Ada Stok', 'game' => 'ML']);
        $this->makeProduct(['supplier_code' => 'B', 'name' => 'Kosong Stok', 'game' => 'ML', 'in_stock' => false]);

        $this->get('/')->assertOk()->assertSee('Ada Stok')->assertDontSee('Kosong Stok');
    }

    public function test_halaman_game_tidak_menampilkan_produk_kosong(): void
    {
        $this->makeProduct(['supplier_code' => 'A', 'name' => 'Ada Stok', 'game' => 'ML']);
        $this->makeProduct(['supplier_code' => 'B', 'name' => 'Kosong Stok', 'game' => 'ML', 'in_stock' => false]);

        $this->get('/game/ML')->assertOk()->assertSee('Ada Stok')->assertDontSee('Kosong Stok');
    }

    public function test_checkout_produk_kosong_404(): void
    {
        $p = $this->makeProduct(['in_stock' => false]);

        $this->get('/product/'.$p->id.'/checkout')->assertNotFound();
    }

    public function test_quote_produk_kosong_404(): void
    {
        $p = $this->makeProduct(['in_stock' => false]);

        $this->postJson('/checkout/quote', ['product_id' => $p->id, 'gateway_code' => 'balance'])
            ->assertNotFound();
    }
}
