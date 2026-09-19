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

    public function test_seed_defaults_mencakup_semua_tugas_terdeklarasi(): void
    {
        CronSetting::seedDefaults();

        // Jangan mengunci jumlah baris: tugas baru akan ditambahkan seiring
        // waktu dan angka tetap membuat test ini merah tanpa bug nyata.
        // Yang penting adalah setiap key yang dideklarasikan punya barisnya.
        $this->assertEqualsCanonicalizing(
            array_keys(CronSetting::DEFAULTS),
            CronSetting::pluck('key')->all(),
        );
    }

    public function test_seed_defaults_idempoten(): void
    {
        CronSetting::seedDefaults();
        CronSetting::seedDefaults();

        $this->assertSame(count(CronSetting::DEFAULTS), CronSetting::count());
    }

    public function test_tugas_rekonsiliasi_aktif_dan_harian(): void
    {
        CronSetting::seedDefaults();

        $row = CronSetting::where('key', CronSetting::KEY_RECONCILE)->first();

        $this->assertNotNull($row, 'Tugas rekonsiliasi harus punya baris, jika tidak CronGate menolaknya selamanya.');
        $this->assertTrue($row->is_active);
        $this->assertSame(1440, $row->interval_minutes);
    }
}
