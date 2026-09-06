<?php

namespace Tests\Feature;

use App\Models\Banner;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

    public function test_image_url_fallback_storage(): void
    {
        $b = Banner::create(['title' => 'X', 'image_path' => 'banners/a.png', 'is_active' => true]);

        $this->assertStringEndsWith('storage/banners/a.png', $b->imageUrl());
    }
}
