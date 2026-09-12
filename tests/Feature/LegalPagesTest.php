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

    public function test_refund_policy_dapat_diakses(): void
    {
        $this->get(route('legal.refund'))
            ->assertOk()
            ->assertSee('Refund Policy')
            ->assertSee('Transaksi yang memenuhi syarat');
    }

    public function test_faq_dapat_diakses(): void
    {
        $this->get(route('support.faq'))
            ->assertOk()
            ->assertSee('Pertanyaan yang sering diajukan')
            ->assertSee('Bagaimana cara melakukan pembelian?');
    }

    public function test_halaman_kontak_dapat_diakses(): void
    {
        $this->get(route('support.contact'))
            ->assertOk()
            ->assertSee('Ada yang bisa kami bantu?')
            ->assertSee('Agar cepat ditangani');
    }

    public function test_footer_memuat_tautan_halaman_legal(): void
    {
        $response = $this->get('/')->assertOk();
        $html = $response->getContent();

        $response
            ->assertSee(route('legal.terms'), false)
            ->assertSee(route('legal.privacy'), false)
            ->assertSee(route('legal.refund'), false)
            ->assertSee(route('support.faq'), false)
            ->assertSee(route('support.contact'), false);
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
