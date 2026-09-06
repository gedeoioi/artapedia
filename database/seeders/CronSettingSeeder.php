<?php

namespace Database\Seeders;

use App\Models\CronSetting;
use Illuminate\Database\Seeder;

class CronSettingSeeder extends Seeder
{
    public function run(): void
    {
        CronSetting::seedDefaults();
    }
}
