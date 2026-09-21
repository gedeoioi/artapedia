<?php

namespace Tests\Feature;

use App\Models\SiteSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Gradien dan hover tab kategori (Top Up Game / Pulsa).
 *
 * Tab-nya harus memakai gradien yang diturunkan dari --primary, bukan nilai
 * oranye yang ditulis tetap: warna utama bisa diganti admin di pengaturan, dan
 * gradien yang di-hardcode akan tetap oranye setelah warnanya berubah.
 *
 * Nilai visual sesungguhnya (gradien terlihat, hover terangkat) hanya bisa
 * dipastikan di browser; test di sini mengunci strukturnya supaya tidak
 * hilang tanpa sengaja.
 */
class CatalogTabStyleTest extends TestCase
{
    use RefreshDatabase;

    public function test_tab_aktif_memakai_gradien_dari_warna_utama(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        // Gradien aktif dibangun dari var(--primary) lewat color-mix.
        $this->assertStringContainsString('color-mix(in srgb, var(--primary)', $html);

        // Ada cadangan warna solid sebelum gradien untuk browser lama.
        $this->assertMatchesRegularExpression(
            '/\.catalog-tabs a\.is-active[\s\S]{0,400}?background: var\(--primary\);\s*background: linear-gradient\(/',
            $html,
            'Tab aktif harus punya cadangan warna solid sebelum gradien.',
        );
    }

    public function test_tab_punya_gradien_saat_diam_dan_saat_hover(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        // Diam: gradien gelap, bukan warna rata.
        $this->assertStringContainsString('linear-gradient(180deg, #2b2b33 0%, #1b1b20 100%)', $html);

        // Hover: gradien lebih terang + border warna utama + cincin fokus.
        $this->assertMatchesRegularExpression(
            '/\.catalog-tabs a:hover[\s\S]{0,500}?background: linear-gradient\(180deg, #3d3d47 0%, #26262d 100%\)/',
            $html,
        );
        $this->assertMatchesRegularExpression(
            '/\.catalog-tabs a:hover[\s\S]{0,600}?border-color: var\(--primary\)/',
            $html,
        );
        $this->assertMatchesRegularExpression(
            '/\.catalog-tabs a:hover[\s\S]{0,700}?0 0 0 3px color-mix\(in srgb, var\(--primary\) 18%, transparent\)/',
            $html,
        );
    }

    public function test_tab_punya_keadaan_ditekan_dan_fokus_keyboard(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        // Ditekan: turun kembali dan mengecil sedikit.
        $this->assertStringContainsString('.catalog-tabs a:active, .catalog-tabs button:active { transform: translateY(0) scale(.975); }', $html);

        // Fokus keyboard harus punya cincin yang terlihat.
        $this->assertMatchesRegularExpression(
            '/\.catalog-tabs a:focus-visible[\s\S]{0,200}?outline: 3px solid/',
            $html,
        );
    }

    /** Tema terang juga memakai gradien, bukan warna rata. */
    public function test_tema_terang_juga_memakai_gradien(): void
    {
        SiteSetting::set('theme', 'light');

        $html = $this->get('/')->assertOk()->getContent();

        $this->assertMatchesRegularExpression(
            '/html\[data-theme="light"\] \.catalog-tabs a,\s*html\[data-theme="light"\] \.catalog-tabs button \{[\s\S]{0,300}?background: linear-gradient\(180deg, #fff 0%, #e7e5e4 100%\)/',
            $html,
            'Tema terang harus punya gradien sendiri.',
        );
        $this->assertMatchesRegularExpression(
            '/html\[data-theme="light"\] \.catalog-tabs a\.is-active[\s\S]{0,400}?color-mix\(in srgb, var\(--primary\)/',
            $html,
        );
    }

    /** Animasi tetap dihormati saat pengguna mematikan gerakan. */
    public function test_transisi_dimatikan_saat_pengguna_memilih_kurangi_gerakan(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        $this->assertMatchesRegularExpression(
            '/@media \(prefers-reduced-motion: reduce\) \{[\s\S]{0,400}?\.catalog-tabs a, \.catalog-tabs button/',
            $html,
        );
    }
}
