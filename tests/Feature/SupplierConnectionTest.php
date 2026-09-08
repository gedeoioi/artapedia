<?php

namespace Tests\Feature;

use App\Models\SupplierConfig;
use App\Services\SupplierConnectionTester;
use App\Suppliers\DigiflazzProvider;
use App\Suppliers\VipResellerProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SupplierConnectionTest extends TestCase
{
    use RefreshDatabase;

    protected function makeSupplier(): SupplierConfig
    {
        return SupplierConfig::create([
            'code' => 'vip-reseller', 'name' => 'VIP',
            'provider_class' => VipResellerProvider::class,
            'is_active' => true, 'is_sandbox' => true, 'priority' => 0,
            'credentials' => ['api_id' => 'ID123', 'api_key' => 'KEY456'],
        ]);
    }

    public function test_koneksi_ok_update_cached_balance(): void
    {
        $supplier = $this->makeSupplier();

        Http::fake([
            'vip-reseller.co.id/*' => Http::sequence()
                ->push(['result' => true, 'data' => ['balance' => 150000]])
                ->push(['result' => true, 'data' => [
                    ['code' => 'ML-100', 'name' => 'ML 100', 'game' => 'ML', 'price' => 10000],
                ]]),
        ]);

        $result = app(SupplierConnectionTester::class)->test($supplier);

        $this->assertTrue($result['ok']);
        $this->assertEquals(150000, $supplier->fresh()->cached_balance);
        $this->assertStringContainsString('Koneksi OK', $result['message']);

        $fresh = $supplier->fresh();
        $this->assertNotNull($fresh->last_test_at);
        $this->assertTrue((bool) $fresh->last_test_ok);
        $this->assertStringContainsString('Langkah 1/2', $fresh->last_test_log);
    }

    public function test_kredensial_salah_dilaporkan_gagal(): void
    {
        $supplier = $this->makeSupplier();

        Http::fake([
            'vip-reseller.co.id/*' => Http::response(['result' => false, 'message' => 'Invalid key'], 200),
        ]);

        $result = app(SupplierConnectionTester::class)->test($supplier);

        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('Invalid key', $result['message']);
        $this->assertEquals(0, $supplier->fresh()->cached_balance);
    }

    public function test_http_error_dilaporkan_gagal_tanpa_exception(): void
    {
        $supplier = $this->makeSupplier();

        Http::fake([
            'vip-reseller.co.id/*' => Http::response(null, 500),
        ]);

        $result = app(SupplierConnectionTester::class)->test($supplier);

        $this->assertFalse($result['ok']);

        $fresh = $supplier->fresh();
        $this->assertNotNull($fresh->last_test_at);
        $this->assertFalse((bool) $fresh->last_test_ok);
        $this->assertNotEmpty($fresh->last_test_log);
    }

    public function test_rahasia_disamarkan_di_log(): void
    {
        $supplier = $this->makeSupplier();

        Http::fake([
            'vip-reseller.co.id/*' => Http::response([
                'result' => true,
                'data' => ['balance' => 100, 'api_key' => 'RAHASIA123', 'nested' => ['secret' => 'TOKENXYZ']],
            ], 200),
        ]);

        $result = app(SupplierConnectionTester::class)->test($supplier);

        $this->assertTrue($result['ok']);
        $log = $supplier->fresh()->last_test_log;
        $this->assertStringNotContainsString('RAHASIA123', $log);
        $this->assertStringNotContainsString('TOKENXYZ', $log);
    }

    public function test_rate_limit_pricelist_digiflazz_tetap_dianggap_koneksi_berhasil(): void
    {
        $supplier = SupplierConfig::create([
            'code' => 'digiflazz',
            'name' => 'Digiflazz',
            'provider_class' => DigiflazzProvider::class,
            'is_active' => true,
            'is_sandbox' => true,
            'priority' => 0,
            'credentials' => ['username' => 'buyer', 'api_key' => 'secret'],
        ]);

        Http::fake([
            'api.digiflazz.com/*' => Http::sequence()
                ->push(['data' => ['deposit' => 250000]])
                ->push(['data' => [
                    'rc' => '83',
                    'message' => 'Anda telah mencapai limitasi pengecekan pricelist, silahkan coba beberapa saat lagi',
                ]]),
        ]);

        $result = app(SupplierConnectionTester::class)->test($supplier);

        $this->assertTrue($result['ok']);
        $this->assertTrue($result['warning']);
        $this->assertStringContainsString('maksimal 1x per 5 menit', $result['message']);
        $this->assertSame(250000, $supplier->fresh()->cached_balance);
        $this->assertTrue((bool) $supplier->fresh()->last_test_ok);
    }
}
