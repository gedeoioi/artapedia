<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\SiteSetting;
use App\Models\User;
use App\Services\ProfitCalculator;
use App\Support\Rupiah;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfitSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_profit_persentase_menghitung_harga_jual(): void
    {
        SiteSetting::set('profit_mode', ProfitCalculator::MODE_PERCENT);
        SiteSetting::set('profit_percent', 7.5);

        $this->assertSame(10_750, app(ProfitCalculator::class)->calculate(10_000));
    }

    public function test_profit_flat_menghitung_harga_jual(): void
    {
        SiteSetting::set('profit_mode', ProfitCalculator::MODE_FLAT);
        SiteSetting::set('profit_flat', 1_500);

        $this->assertSame(11_500, app(ProfitCalculator::class)->calculate(10_000));
        $this->assertSame(0, app(ProfitCalculator::class)->calculate(0));
    }

    public function test_pengaturan_bisa_diterapkan_ke_produk_lama(): void
    {
        $product = Product::create([
            'supplier_code' => 'PROFIT-1',
            'name' => 'Produk Profit',
            'game' => 'Game Profit',
            'cost_basic' => 10_000,
            'cost_premium' => 9_000,
            'cost_special' => 8_000,
            'price_guest' => 1,
            'price_biasa' => 1,
            'price_vip' => 1,
            'is_active' => true,
            'in_stock' => true,
        ]);
        SiteSetting::set('profit_mode', ProfitCalculator::MODE_FLAT);
        SiteSetting::set('profit_flat', 1_000);

        $updated = app(ProfitCalculator::class)->applyToExistingProducts();

        $this->assertSame(1, $updated);
        $this->assertSame(11_000, $product->fresh()->price_guest);
        $this->assertSame(10_000, $product->fresh()->price_biasa);
        $this->assertSame(9_000, $product->fresh()->price_vip);
    }

    public function test_format_rupiah_menggunakan_simbol_dan_pemisah_indonesia(): void
    {
        $this->assertSame('Rp 1.250.000', Rupiah::format(1_250_000));
    }

    public function test_admin_bisa_membuka_menu_atur_profit(): void
    {
        $admin = User::factory()->create(['level' => 'admin']);

        $this->actingAs($admin)
            ->get('/admin/profit-settings')
            ->assertOk()
            ->assertSee('Atur Profit Produk');
    }
}
