<?php

namespace Tests\Feature;

use App\Models\Banner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BannerSliderTest extends TestCase
{
    use RefreshDatabase;

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
            ->assertSee('aspect-[9/2]', false)
            ->assertDontSee('h-40 md:h-64', false);
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
