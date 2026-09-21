<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Header untuk pengguna yang sudah login.
 *
 * Menu teks "Member" dihapus karena tombol "Member Area" di kanan menuju
 * halaman yang sama — dua kontrol dengan tujuan identik membuat header ramai
 * tanpa menambah kemampuan. Sebagai gantinya, tombol "Keluar" ditaruh di
 * sebelah "Member Area" supaya pembeli bisa logout langsung dari header.
 */
class HeaderMemberActionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_tamu_melihat_tombol_masuk_tanpa_keluar(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('>Masuk</a>', $html);
        $this->assertStringNotContainsString('class="btn-logout', $html);
        $this->assertStringNotContainsString('Member Area', $html);
    }

    public function test_pengguna_login_melihat_member_area_dan_keluar(): void
    {
        $user = User::factory()->create(['level' => 'member']);

        $html = $this->actingAs($user)->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('Member Area', $html);
        $this->assertStringContainsString('class="btn-logout', $html);
        $this->assertStringContainsString('>Keluar</button>', $html);

        // Tamu tidak boleh melihat tombol keluar, dan sebaliknya.
        $this->assertStringNotContainsString('>Masuk</a>', $html);
    }

    /** Menu teks "Member" sudah tidak dipakai lagi. */
    public function test_menu_teks_member_sudah_tidak_ada(): void
    {
        $user = User::factory()->create(['level' => 'member']);

        $html = $this->actingAs($user)->get('/')->assertOk()->getContent();

        // Menu nav lama: <span>Member</span> di dalam navlink.
        $this->assertStringNotContainsString('<span>Member</span>', $html);

        // Tautan dashboard tetap ada — lewat tombol "Member Area".
        $this->assertStringContainsString(route('member.dashboard'), $html);
    }

    /**
     * Tombol keluar harus berupa form POST dengan CSRF.
     *
     * Rute logout hanya menerima POST; tombol yang dikirim sebagai tautan GET
     * akan menghasilkan 405, dan tanpa token akan ditolak 419.
     */
    public function test_tombol_keluar_memakai_form_post_dengan_csrf(): void
    {
        $user = User::factory()->create(['level' => 'member']);

        $html = $this->actingAs($user)->get('/')->assertOk()->getContent();

        $this->assertMatchesRegularExpression(
            '/<form method="POST" action="[^"]*\/logout">\s*<input type="hidden" name="_token"/s',
            $html,
            'Tombol keluar harus POST + CSRF, bukan tautan GET.',
        );
    }

    /** Logout benar-benar mengakhiri sesi dan mengembalikan header ke keadaan tamu. */
    public function test_keluar_mengakhiri_sesi(): void
    {
        $user = User::factory()->create(['level' => 'member']);

        $this->actingAs($user)->post(route('logout'))->assertRedirect();

        $this->assertGuest();

        $html = $this->get('/')->assertOk()->getContent();
        $this->assertStringContainsString('>Masuk</a>', $html);
        $this->assertStringNotContainsString('class="btn-logout', $html);
    }
}
