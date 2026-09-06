<?php

namespace Tests\Feature;

use App\Models\CronSetting;
use App\Support\CronGate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CronSettingTest extends TestCase
{
    use RefreshDatabase;

    public function test_nonaktif_tidak_jalan(): void
    {
        CronSetting::create([
            'key' => CronSetting::KEY_POLL_PROCESSING,
            'label' => 'test',
            'interval_minutes' => 1,
            'is_active' => false,
        ]);

        $this->assertFalse(CronGate::allows(CronSetting::KEY_POLL_PROCESSING));
    }

    public function test_interval_menghormati_last_run(): void
    {
        CronSetting::create([
            'key' => CronSetting::KEY_POLL_GATEWAY,
            'label' => 'test',
            'interval_minutes' => 60,
            'is_active' => true,
            'last_run_at' => now(),
        ]);

        $this->assertFalse(CronGate::allows(CronSetting::KEY_POLL_GATEWAY));

        CronSetting::where('key', CronSetting::KEY_POLL_GATEWAY)
            ->update(['last_run_at' => now()->subMinutes(61)]);

        $this->assertTrue(CronGate::allows(CronSetting::KEY_POLL_GATEWAY));
        $this->assertFalse(CronGate::allows(CronSetting::KEY_POLL_GATEWAY));
    }

    public function test_seed_defaults_4_tugas(): void
    {
        CronSetting::seedDefaults();

        $this->assertEquals(4, CronSetting::count());
    }
}
