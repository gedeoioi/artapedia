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
        $response->assertSee('>Top Up Game</button>', false)
            ->assertSee('>Pulsa</button>', false)
            ->assertDontSee('>Paket Data</button>', false)
            ->assertDontSee('>Voucher</button>', false);
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

    public function test_grid_kategori_beranda_menampilkan_satu_baris_dan_tombol_tampilkan_lainnya(): void
    {
        foreach (range(1, 21) as $number) {
            $this->makeProduct([
                'supplier_code' => 'GAME-'.$number,
                'game' => 'Game '.str_pad((string) $number, 2, '0', STR_PAD_LEFT),
            ]);
        }

        $response = $this->get('/')->assertOk();

        // Daftar tetap dipecah 5 per halaman (satu baris grid desktop).
        $pages = $response->viewData('categoryPagesByType')[Product::TYPE_GAME];
        $this->assertCount(5, $pages);
        $this->assertCount(5, $pages[0]);

        // Semua kategori dirender, tapi hanya satu baris yang terlihat.
        $this->assertCount(21, $pages->flatten(1));

        $response->assertSee('Tampilkan Lainnya')
            ->assertSee('tampilkanLainnya(', false)
            ->assertSee('tampilKategori(', false)
            ->assertSee('category-page-transition')
            ->assertSee('catalog-more-button');

        // Tombol lama yang menggeser halaman sudah tidak dipakai.
        $response->assertDontSee('Lihat Selanjutnya')
            ->assertDontSee('changeCategoryPage(', false)
            ->assertDontSee('pageByType', false);
    }

    /**
     * Animasi "Tampilkan Lainnya" harus bertahap, bukan semua kartu sekaligus.
     *
     * Diukur di browser: kartu baru mulai pada 0/31/82/132/165 ms, jadi
     * jeda 45 ms per kartu memang terpakai.
     */
    public function test_animasi_kartu_baru_bertahap_dan_aman(): void
    {
        foreach (range(1, 21) as $number) {
            $this->makeProduct([
                'supplier_code' => 'ANIM-'.$number,
                'game' => 'Animasi '.str_pad((string) $number, 2, '0', STR_PAD_LEFT),
            ]);
        }

        $response = $this->get('/')->assertOk();

        // Kartu yang baru muncul memakai kelas + jeda per kartu.
        $response->assertSee('category-card-muncul')
            ->assertSee('kartuBaru(', false)
            ->assertSee('jedaAnimasi(', false)
            ->assertSee('@keyframes category-card-masuk', false)
            ->assertSee('animation: category-card-masuk', false);

        // Jeda dihitung dari urutan kartu, bukan nilai tetap.
        $response->assertSee('(urutan * 45)', false);

        // Animasi harus dihormati saat pengguna mematikan gerakan.
        $response->assertSee('prefers-reduced-motion', false);

        // Kartu baru hanya ditandai selama animasi berlangsung.
        $response->assertSee('baru[type] = -1', false);

        // Tombol memakai satu label reaktif — dua span x-show sempat membuat
        // "Tampilkan Lainnya..." dan "Memuat..." tampil bersamaan.
        $this->assertStringNotContainsString('<span x-show="!sedangMemuat">', $response->getContent());
    }

    /**
     * Jeda animasi harus diberikan lewat objek :style, bukan string.
     *
     * Bentuk string mengganti SELURUH atribut style, sehingga display:none
     * milik x-show terhapus dan semua kartu tersembunyi ikut tampil — satu
     * klik langsung membuka seluruh daftar.
     */
    public function test_jeda_animasi_tidak_menghapus_display_dari_x_show(): void
    {
        foreach (range(1, 21) as $number) {
            $this->makeProduct([
                'supplier_code' => 'STYLE-'.$number,
                'game' => 'Gaya '.str_pad((string) $number, 2, '0', STR_PAD_LEFT),
            ]);
        }

        $content = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString(':style="{ animationDelay:', $content);
        $this->assertStringNotContainsString(":style=\"'animation-delay: '", $content);
    }

    /**
     * Tombol harus tahu berapa kategori yang masih tersembunyi, kalau tidak
     * tombolnya tetap tampil setelah semua kategori ditampilkan.
     */
    public function test_jumlah_kategori_per_tipe_dikirim_ke_view(): void
    {
        foreach (range(1, 12) as $number) {
            $this->makeProduct([
                'supplier_code' => 'GAME-'.$number,
                'game' => 'Game '.str_pad((string) $number, 2, '0', STR_PAD_LEFT),
            ]);
        }

        $response = $this->get('/')->assertOk();

        $total = $response->viewData('totalKategoriPerTipe');
        $this->assertSame(12, $total[Product::TYPE_GAME]);
        $this->assertSame(5, $response->viewData('visibleCategories'));
    }
}
