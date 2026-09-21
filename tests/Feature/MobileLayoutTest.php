<?php

namespace Tests\Feature;

use App\Models\SiteSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Header dan form di beranda harus muat di layar ponsel.
 *
 * Sebelumnya dua masalah membuat seluruh halaman bisa digeser ke samping:
 *  1. Logo gambar DAN teks nama situs tampil bersamaan, jadi blok kiri memakan
 *    ~230px dan tombol kanan terdorong keluar layar.
 *  2. Form "Cek Transaksi" menaruh input dan tombol dalam satu baris flex tanpa
 *    izin menyusut, sehingga tombolnya keluar dari card.
 *
 * Test di sini mengunci struktur yang mencegahnya (kelas responsif), karena
 * pengukuran lebar sesungguhnya hanya bisa dilakukan di browser.
 */
class MobileLayoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_header_menyembunyikan_teks_situs_di_ponsel_saat_ada_logo(): void
    {
        SiteSetting::set('logo_path', 'site/logo.png');

        $html = $this->get('/')->assertOk()->getContent();

        // Logo gambar tidak boleh tumbuh (shrink-0) dan blok kiri boleh menyusut.
        $this->assertStringContainsString('h-8 w-auto rounded shrink-0 brand-logo', $html);

        // Teks nama situs hanya tampil dari breakpoint sm ke atas.
        $this->assertMatchesRegularExpression(
            '/<span class="hidden sm:inline truncate">\s*ArtaPedia\s*<\/span>/',
            $html,
            'Teks nama situs harus disembunyikan di ponsel saat logo gambar dipakai.',
        );
    }

    public function test_header_tetap_menampilkan_teks_situs_saat_tidak_ada_logo(): void
    {
        SiteSetting::set('logo_path', null);

        $html = $this->get('/')->assertOk()->getContent();

        // Tanpa logo gambar, teks nama situs harus tetap tampil di semua ukuran.
        $this->assertMatchesRegularExpression(
            '/<span class="truncate">\s*ArtaPedia\s*<\/span>/',
            $html,
            'Tanpa logo gambar, nama situs tidak boleh ikut disembunyikan.',
        );
    }

    public function test_form_cek_transaksi_menumpuk_di_ponsel(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        // Menumpuk di ponsel, sebaris dari sm ke atas.
        $this->assertStringContainsString('flex flex-col sm:flex-row gap-2 sm:max-w-md', $html);

        // Input dan tombol selebar penuh di ponsel.
        $this->assertStringContainsString('card w-full min-w-0 px-3 py-2 text-sm', $html);
        $this->assertStringContainsString('btn-primary w-full sm:w-auto', $html);
    }

    /** Tombol header tidak boleh membungkus jadi dua baris. */
    public function test_tombol_header_tidak_membungkus_teks(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        $this->assertMatchesRegularExpression(
            '/btn-primary px-3 sm:px-5 py-2 text-sm whitespace-nowrap">Masuk</',
            $html,
        );
    }
}
