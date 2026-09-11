<?php

namespace Tests\Feature;

use App\Models\GameIcon;
use App\Models\PaymentGatewayConfig;
use App\Models\Product;
use App\Payments\XenditGateway;
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
        $this->makeProduct(['supplier_code' => 'A', 'name' => 'Ada Stok', 'game' => 'Game Ada']);
        $this->makeProduct(['supplier_code' => 'B', 'name' => 'Kosong Stok', 'game' => 'Game Kosong', 'in_stock' => false]);

        // Beranda hanya menampilkan nama kategori (bukan nama produk).
        $this->get('/')->assertOk()->assertSee('Game Ada')->assertDontSee('Game Kosong');
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

    public function test_ringkasan_checkout_menampilkan_biaya_layanan_sesuai_pengaturan_gateway(): void
    {
        $product = $this->makeProduct([
            'name' => '86 Diamonds',
            'game' => 'Mobile Legends',
            'price_guest' => 12000,
        ]);
        PaymentGatewayConfig::create([
            'code' => 'xendit',
            'name' => 'All Payment',
            'gateway_class' => XenditGateway::class,
            'is_active' => true,
            'is_sandbox' => true,
            'sort_order' => 0,
            'credentials' => [],
            'fee_flat' => 2500,
            'fee_percent' => 10,
        ]);

        $this->get(route('checkout.show', $product))
            ->assertOk()
            ->assertSee('Ringkasan pesanan', false)
            ->assertSee('Metode Pembayaran')
            ->assertSee('Jumlah Pembelian')
            ->assertSee('Biaya layanan')
            ->assertSee('Total Pembayaran')
            ->assertDontSee('Fee gateway');

        $this->postJson(route('checkout.quote'), [
            'product_id' => $product->id,
            'gateway_code' => 'xendit',
            'quantity' => 2,
        ])->assertOk()->assertJson([
            'sell_price' => 12000,
            'subtotal' => 24000,
            'gateway_fee' => 4900,
            'total' => 28900,
            'checkout_total' => 28900,
        ]);
    }

    public function test_beranda_memisahkan_kategori_berdasarkan_tipe_produk(): void
    {
        $this->makeProduct(['supplier_code' => 'GAME', 'game' => 'Mobile Legends', 'product_type' => Product::TYPE_GAME]);
        $this->makeProduct(['supplier_code' => 'PULSA', 'game' => 'Telkomsel', 'product_type' => Product::TYPE_PULSA]);
        $this->makeProduct(['supplier_code' => 'DATA', 'game' => 'Internet Telkomsel', 'product_type' => Product::TYPE_DATA]);
        $this->makeProduct(['supplier_code' => 'VOUCHER', 'game' => 'Google Play', 'product_type' => Product::TYPE_VOUCHER]);

        $response = $this->get('/?type=pulsa');

        $response->assertOk();
        $this->assertSame(Product::TYPE_PULSA, $response->viewData('activeType'));
        $this->assertSame(['Telkomsel'], $response->viewData('games')->pluck('game')->all());
    }

    public function test_favorit_yang_dipilih_admin_menggantikan_fallback_otomatis(): void
    {
        $this->makeProduct(['supplier_code' => 'A', 'game' => 'Kategori Biasa']);
        $this->makeProduct(['supplier_code' => 'B', 'game' => 'Kategori Pilihan']);
        GameIcon::create([
            'game_name' => 'Kategori Pilihan',
            'slug' => 'kategori-pilihan',
            'is_active' => true,
            'is_favorite' => true,
            'favorite_order' => 1,
        ]);

        $favorites = $this->get('/')->assertOk()->viewData('favorites');

        $this->assertSame(['Kategori Pilihan'], $favorites->pluck('game')->all());
    }

    public function test_tipe_kategori_tidak_valid_kembali_ke_game(): void
    {
        $this->makeProduct(['supplier_code' => 'GAME', 'game' => 'Kategori Game']);

        $response = $this->get('/?type=tidak-valid');

        $response->assertOk()->assertSee('Kategori Game');
        $this->assertSame(Product::TYPE_GAME, $response->viewData('activeType'));
    }

    public function test_filter_kategori_di_beranda_memakai_tombol_tanpa_navigasi(): void
    {
        $this->makeProduct(['supplier_code' => 'GAME', 'game' => 'Kategori Game']);

        $this->get('/')
            ->assertOk()
            ->assertSee('type="button"', false)
            ->assertSee('@click="activeType =', false)
            ->assertSee('id="category-panel-game"', false);
    }

    public function test_setiap_grid_kategori_beranda_konsisten_dibagi_per_sepuluh_item(): void
    {
        foreach (range(1, 21) as $number) {
            $this->makeProduct([
                'supplier_code' => 'GAME-'.$number,
                'game' => 'Game '.str_pad((string) $number, 2, '0', STR_PAD_LEFT),
            ]);
        }

        $response = $this->get('/')->assertOk();
        $pages = $response->viewData('categoryPagesByType')[Product::TYPE_GAME];

        $this->assertCount(3, $pages);
        $this->assertCount(10, $pages[0]);
        $this->assertCount(10, $pages[1]);
        $this->assertCount(1, $pages[2]);
        $response->assertSee('changeCategoryPage(', false)
            ->assertSee('Lihat Selanjutnya')
            ->assertSee('category-page-transition')
            ->assertSee('catalog-more-button');
    }
}
