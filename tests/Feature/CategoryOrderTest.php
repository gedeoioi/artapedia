<?php

namespace Tests\Feature;

use App\Filament\Resources\GameIcons\Pages\EditGameIcon;
use App\Filament\Resources\GameIcons\Pages\ListGameIcons;
use App\Models\GameIcon;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Urutan kategori di beranda diatur admin lewat kolom "Urutan tampil".
 *
 * Sebelumnya grid "Semua Kategori" selalu urut abjad, sehingga kategori penting
 * tidak bisa ditaruh di depan. Angka lebih kecil tampil lebih dahulu; kategori
 * yang belum diatur (0) diletakkan setelahnya menurut abjad.
 */
class CategoryOrderTest extends TestCase
{
    use RefreshDatabase;

    protected function makeProduct(string $game, string $type = Product::TYPE_GAME): Product
    {
        return Product::create([
            'supplier_code' => 'X'.uniqid(), 'name' => $game.' 100', 'game' => $game,
            'product_type' => $type,
            'cost_basic' => 100, 'cost_premium' => 100, 'cost_special' => 100,
            'price_guest' => 120, 'price_biasa' => 115, 'price_vip' => 110,
            'is_active' => true, 'in_stock' => true,
        ]);
    }

    protected function setOrder(string $game, int $order): void
    {
        GameIcon::updateOrCreate(
            ['game_name' => $game],
            ['slug' => str()->slug($game), 'display_order' => $order, 'is_active' => true],
        );
    }

    /** @return list<string> */
    protected function kategoriBeranda(string $type = Product::TYPE_GAME): array
    {
        return $this->get('/?type='.$type)
            ->assertOk()
            ->viewData('categoryPagesByType')[$type]
            ->flatten(1)
            ->pluck('game')
            ->all();
    }

    public function test_tanpa_urutan_kategori_tetap_urut_abjad(): void
    {
        foreach (['Zelda', 'Arena', 'Mobile Legends'] as $game) {
            $this->makeProduct($game);
        }

        $this->assertSame(['Arena', 'Mobile Legends', 'Zelda'], $this->kategoriBeranda());
    }

    public function test_urutan_angka_menaruh_kategori_di_depan(): void
    {
        foreach (['Zelda', 'Arena', 'Mobile Legends'] as $game) {
            $this->makeProduct($game);
        }

        // Zelda diberi angka terkecil, jadi harus tampil paling depan.
        $this->setOrder('Zelda', 1);

        $this->assertSame(['Zelda', 'Arena', 'Mobile Legends'], $this->kategoriBeranda());
    }

    public function test_kategori_tanpa_urutan_diletakkan_setelah_yang_punya_urutan(): void
    {
        foreach (['Zelda', 'Arena', 'Mobile Legends', 'PUBG'] as $game) {
            $this->makeProduct($game);
        }

        $this->setOrder('Zelda', 5);
        $this->setOrder('PUBG', 1);

        // Dua yang punya urutan di depan (terkecil dahulu), sisanya abjad.
        $this->assertSame(['PUBG', 'Zelda', 'Arena', 'Mobile Legends'], $this->kategoriBeranda());
    }

    public function test_urutan_berlaku_terpisah_untuk_setiap_tipe(): void
    {
        $this->makeProduct('Zelda');
        $this->makeProduct('Arena');
        $this->makeProduct('Telkomsel', Product::TYPE_PULSA);
        $this->makeProduct('Indosat', Product::TYPE_PULSA);

        $this->setOrder('Zelda', 1);
        $this->setOrder('Indosat', 1);

        $this->assertSame(['Zelda', 'Arena'], $this->kategoriBeranda(Product::TYPE_GAME));
        $this->assertSame(['Indosat', 'Telkomsel'], $this->kategoriBeranda(Product::TYPE_PULSA));
    }

    public function test_urutan_diabaikan_saat_kategori_tidak_punya_produk_aktif(): void
    {
        $this->makeProduct('Zelda');
        $this->makeProduct('Arena');

        // Urutan diberikan ke kategori yang produknya kosong.
        $this->setOrder('Kategori Kosong', 1);
        Product::where('game', 'Arena')->update(['in_stock' => false]);

        $this->assertSame(['Zelda'], $this->kategoriBeranda());
    }

    public function test_halaman_kategori_memakai_urutan_yang_sama(): void
    {
        foreach (['Zelda', 'Arena', 'Mobile Legends'] as $game) {
            $this->makeProduct($game);
        }

        $this->setOrder('Mobile Legends', 1);

        $games = $this->get('/kategori')->assertOk()->viewData('games');
        $this->assertSame(['Mobile Legends', 'Arena', 'Zelda'], collect($games->items())->pluck('game')->all());
    }

    public function test_kategori_baru_otomatis_mendapat_urutan_berikutnya(): void
    {
        $this->setOrder('Zelda', 7);

        // Kategori baru dibuat tanpa menyebut urutan (seperti saat sync produk).
        $baru = GameIcon::create([
            'game_name' => 'Game Baru', 'slug' => 'game-baru', 'is_active' => true,
        ]);

        $this->assertSame(8, $baru->fresh()->display_order, 'Kategori baru harus lanjut setelah urutan terbesar.');
    }

    public function test_urutan_yang_diisi_manual_tidak_ditimpa(): void
    {
        $icon = GameIcon::create([
            'game_name' => 'Zelda', 'slug' => 'zelda', 'is_active' => true, 'display_order' => 3,
        ]);

        $this->assertSame(3, $icon->fresh()->display_order);
    }

    public function test_nama_kategori_bertanda_kutip_tidak_merusak_query(): void
    {
        $this->makeProduct("Tom's Game");
        $this->makeProduct('Arena');

        $this->setOrder("Tom's Game", 1);

        $this->assertSame(["Tom's Game", 'Arena'], $this->kategoriBeranda());
    }

    /** Urutan harus tetap berlaku saat pencarian dipakai. */
    public function test_urutan_tetap_berlaku_saat_pencarian(): void
    {
        foreach (['Zelda', 'Zelda Mobile', 'Arena'] as $game) {
            $this->makeProduct($game);
        }

        $this->setOrder('Zelda Mobile', 1);

        $games = $this->get('/?q=Zelda')->assertOk()
            ->viewData('categoryPagesByType')[Product::TYPE_GAME]
            ->flatten(1)
            ->pluck('game')
            ->all();

        $this->assertSame(['Zelda Mobile', 'Zelda'], $games);
    }

    /** Kolom urutan harus tersedia di form admin, bukan hanya di database. */
    public function test_form_admin_kategori_punya_kolom_urutan_tampil(): void
    {
        $form = file_get_contents(app_path('Filament/Resources/GameIcons/Schemas/GameIconForm.php'));

        $this->assertStringContainsString("TextInput::make('display_order')", $form);
        $this->assertStringContainsString('Urutan tampil di beranda', $form);
    }

    /**
     * Panel admin harus benar-benar bisa MENYIMPAN urutannya.
     *
     * Test yang hanya memeriksa keberadaan file form membuktikan kolomnya ada di
     * kode, bukan bahwa operator bisa mengisinya. Di sini formulirnya diisi dan
     * disimpan lewat Livewire, lalu hasilnya dibaca dari database.
     */
    public function test_admin_bisa_menyimpan_urutan_kategori_lewat_form(): void
    {
        $this->actingAs($this->adminUser());

        $icon = GameIcon::create([
            'game_name' => 'Zelda', 'slug' => 'zelda', 'is_active' => true, 'display_order' => 9,
        ]);

        Livewire::test(EditGameIcon::class, ['record' => $icon->getKey()])
            ->fillForm(['display_order' => 1])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(1, $icon->fresh()->display_order);
    }

    /** Kolom urutan harus tampil di daftar kategori admin. */
    public function test_tabel_admin_kategori_menampilkan_kolom_urutan(): void
    {
        $this->actingAs($this->adminUser());

        $icon = GameIcon::create([
            'game_name' => 'Zelda', 'slug' => 'zelda', 'is_active' => true, 'display_order' => 4,
        ]);

        Livewire::test(ListGameIcons::class)
            ->assertTableColumnStateSet('display_order', 4, $icon->getKey());
    }

    /**
     * Halaman admin kategori harus benar-benar terbuka, bukan hanya Livewire-nya.
     *
     * Kolom baru di form bisa memakai namespace atau komponen yang salah dan
     * hanya meledak saat halaman dirender; Livewire::test dan lint tetap hijau.
     */
    public function test_halaman_admin_kategori_terbuka(): void
    {
        $admin = $this->adminUser();
        $icon = GameIcon::create([
            'game_name' => 'Zelda', 'slug' => 'zelda', 'is_active' => true, 'display_order' => 2,
        ]);

        $this->actingAs($admin)->get('/admin/game-icons')->assertOk();
        $this->actingAs($admin)->get('/admin/game-icons/'.$icon->getKey().'/edit')->assertOk();
    }

    private function adminUser(): User
    {
        $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

        $user = User::factory()->create(['level' => 'admin']);
        $user->assignRole($role);

        return $user;
    }
}
