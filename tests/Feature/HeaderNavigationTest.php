<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HeaderNavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_header_menampilkan_menu_topup_dan_riwayat_dengan_ikon(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertSee('aria-label="Topup"', false)
            ->assertSee('aria-label="Riwayat transaksi"', false)
            ->assertSee('<span>Riwayat</span>', false)
            ->assertSee('class="navlink"', false);
    }

    public function test_tombol_dashboard_member_berlabel_member_area(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('home'))
            ->assertOk()
            ->assertSee('Member Area')
            ->assertDontSee('>Dasbor</a>', false);
    }
}
