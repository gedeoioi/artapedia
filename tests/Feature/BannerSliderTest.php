<?php

namespace Tests\Feature;

use App\Filament\Forms\Components\BannerImageUpload;
use App\Models\Banner;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BannerSliderTest extends TestCase
{
    use RefreshDatabase;

    public function test_upload_banner_memakai_crop_tanpa_memvalidasi_rasio_file_asli(): void
    {
        $upload = BannerImageUpload::make('image_path');

        $this->assertSame('9:2', $upload->getAutomaticallyCropImagesAspectRatio());
        $this->assertNull($upload->getImageAspectRatio());
    }

    /**
     * Keterangan ukuran di admin harus menyebut angka yang benar-benar dipakai
     * form (1152x256, maks 3 MB) — kalau tidak, operator mengunggah berdasarkan
     * angka yang salah.
     */
    public function test_keterangan_ukuran_upload_sesuai_konfigurasi(): void
    {
        $upload = BannerImageUpload::make('image_path')
            ->automaticallyResizeImagesToWidth('1152')
            ->automaticallyResizeImagesToHeight('256')
            ->maxSize(3072);

        $this->assertSame('1152', $upload->getAutomaticallyResizeImagesWidth());
        $this->assertSame('256', $upload->getAutomaticallyResizeImagesHeight());
        $this->assertSame(3072, $upload->getMaxSize());
        $this->assertSame('9:2', $upload->getAutomaticallyCropImagesAspectRatio());
    }

    /**
     * Form banner harus bisa dirender — placeholder keterangan perangkat
     * memakai HTML mentah, dan salah kelas/namespace hanya meledak saat render.
     */
    public function test_form_banner_di_admin_render(): void
    {
        $admin = User::factory()->create(['level' => 'admin']);
        $admin->assignRole(Role::firstOrCreate(['name' => 'admin']));

        $this->actingAs($admin)->get('/admin/banners/create')->assertOk();
    }

    /**
     * Keterangan responsif harus benar-benar sampai ke layar admin, bukan
     * hanya niat di kode.
     */
    public function test_keterangan_responsif_muncul_di_form_admin(): void
    {
        $admin = User::factory()->create(['level' => 'admin']);
        $admin->assignRole(Role::firstOrCreate(['name' => 'admin']));

        $res = $this->actingAs($admin)->get('/admin/banners/create');

        $res->assertOk();
        $res->assertSee('1152 x 256', false);
        $res->assertSee('Ponsel', false);
        $res->assertSee('Tablet', false);
        $res->assertSee('Desktop', false);
        // Diagram area aman harus ikut terkirim — kalau tidak, operator hanya
        // dapat angka tanpa tahu bagian gambar mana yang selamat di ponsel.
        $res->assertSee('AREA AMAN', false);
        $res->assertSee('52.7%', false);
    }

    public function test_beranda_menampilkan_banner_aktif_berurutan(): void
    {
        Banner::create(['title' => 'Kedua', 'sort_order' => 2, 'is_active' => true]);
        Banner::create(['title' => 'Pertama', 'sort_order' => 1, 'is_active' => true]);
        Banner::create(['title' => 'Nonaktif', 'sort_order' => 0, 'is_active' => false]);

        $res = $this->get('/');

        $res->assertOk();
        $res->assertSee('Pertama');
        $res->assertSee('Kedua');
        $res->assertDontSee('Nonaktif');
        $this->assertLessThan(
            strpos($res->getContent(), 'Kedua'),
            strpos($res->getContent(), 'Pertama'),
            'Urutan banner salah'
        );
    }

    public function test_beranda_tanpa_banner_tetap_jalan(): void
    {
        $this->get('/')->assertOk();
    }

    public function test_area_banner_memakai_rasio_yang_sama_dengan_upload(): void
    {
        Banner::create(['title' => 'Promo', 'image_path' => 'banners/promo.jpg', 'is_active' => true]);

        $this->get('/')
            ->assertOk()
            ->assertSee('banner-media', false)
            ->assertDontSee('h-40 md:h-64', false);
    }

    /**
     * Banner harus punya lantai tinggi supaya gambar tetap terbaca di layar
     * sempit: rasio 9:2 pada lebar 356 px hanya menghasilkan tinggi ~79 px.
     */
    public function test_banner_punya_tinggi_minimum_untuk_layar_sempit(): void
    {
        Banner::create(['title' => 'Promo', 'image_path' => 'banners/promo.jpg', 'is_active' => true]);

        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('aspect-ratio: 9 / 2', $html);
        $this->assertStringContainsString('min-height: 150px', $html);
        // Lantai dilepas di desktop, tempat rasio 9:2 sudah memberi tinggi cukup.
        $this->assertStringContainsString('@media (min-width: 1024px)', $html);
    }

    /**
     * Teks banner tidak boleh disembunyikan di ponsel — sebelumnya subjudul dan
     * tombol hilang di bawah 640 px, jadi banner ponsel hanya berisi judul.
     */
    public function test_teks_banner_tampil_juga_di_ponsel(): void
    {
        Banner::create([
            'title' => 'Promo Uji', 'subtitle' => 'Berlaku akhir bulan',
            'button_text' => 'Lihat Promo', 'is_active' => true,
        ]);

        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('banner-subtitle', $html);
        $this->assertStringContainsString('banner-cta', $html);
        // Kelas lama yang menyembunyikan teks di ponsel harus hilang.
        $this->assertStringNotContainsString('hidden sm:block', $html);
        $this->assertStringNotContainsString('hidden sm:inline-block', $html);
    }

    /**
     * Pergeseran slide dihitung sebagai persen dari LEBAR TRACK, bukan lebar
     * frame. Track selebar total x frame, jadi menggeser i*100% berarti
     * menggeser i x total x lebar frame — dua kali terlalu jauh pada dua slide,
     * dan hasilnya banner terlihat kosong setelah slide berganti.
     *
     * Rumus yang benar: i / total * 100.
     */
    public function test_pergeseran_slide_memakai_persen_dari_lebar_track(): void
    {
        Banner::create(['title' => 'Satu', 'sort_order' => 0, 'is_active' => true]);
        Banner::create(['title' => 'Dua', 'sort_order' => 1, 'is_active' => true]);

        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('(i / total * 100)', $html);
        $this->assertStringNotContainsString('(i * 100)', $html);

        // Lebar track dan lebar tiap slide harus konsisten dengan total slide.
        $this->assertStringContainsString("width: ' + (total * 100) + '%'", $html);
        $this->assertStringContainsString('width: 50%', $html);
    }

    public function test_image_url_fallback_storage(): void
    {
        $b = Banner::create(['title' => 'X', 'image_path' => 'banners/a.png', 'is_active' => true]);

        $this->assertStringEndsWith('storage/banners/a.png', $b->imageUrl());
    }

    public function test_gambar_lama_dihapus_saat_banner_diganti(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('banners/lama.jpg', 'lama');
        Storage::disk('public')->put('banners/baru.jpg', 'baru');
        $banner = Banner::create(['title' => 'Promo', 'image_path' => 'banners/lama.jpg']);

        $banner->update(['image_path' => 'banners/baru.jpg']);

        Storage::disk('public')->assertMissing('banners/lama.jpg');
        Storage::disk('public')->assertExists('banners/baru.jpg');
    }

    public function test_gambar_dihapus_bersama_banner(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('banners/promo.jpg', 'gambar');
        $banner = Banner::create(['title' => 'Promo', 'image_path' => 'banners/promo.jpg']);

        $banner->delete();

        Storage::disk('public')->assertMissing('banners/promo.jpg');
    }

    public function test_url_gambar_eksternal_tidak_dihapus(): void
    {
        Storage::fake('public');
        $banner = Banner::create(['title' => 'Promo', 'image_path' => 'https://example.com/banner.jpg']);

        $banner->delete();

        Storage::disk('public')->assertDirectoryEmpty('/');
    }
}
