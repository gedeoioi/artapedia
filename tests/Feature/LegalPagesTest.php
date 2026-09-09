<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LegalPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_terms_and_conditions_dapat_diakses(): void
    {
        $this->get(route('legal.terms'))
            ->assertOk()
            ->assertSee('Terms &amp; Conditions', false)
            ->assertSee('Pemesanan dan pembayaran');
    }

    public function test_privacy_policy_dapat_diakses(): void
    {
        $this->get(route('legal.privacy'))
            ->assertOk()
            ->assertSee('Privacy Policy')
            ->assertSee('Data yang kami kumpulkan');
    }

    public function test_footer_memuat_tautan_halaman_legal(): void
    {
        $response = $this->get('/')->assertOk();
        $html = $response->getContent();

        $response
            ->assertSee(route('legal.terms'), false)
            ->assertSee(route('legal.privacy'), false);
        $this->assertMatchesRegularExpression(
            '/data-footer-section="bantuan".*Terms &amp; Conditions.*Privacy Policy/s',
            $html,
        );
        $this->assertDoesNotMatchRegularExpression(
            '/data-footer-section="layanan".*Terms &amp; Conditions.*data-footer-section="bantuan"/s',
            $html,
        );
    }
}
