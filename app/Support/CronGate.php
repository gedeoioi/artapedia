<?php

namespace App\Support;

use App\Models\CronSetting;
use Illuminate\Support\Facades\Schema;

class CronGate
{
    public static function allows(string $key): bool
    {
        try {
            if (! Schema::hasTable('cron_settings')) {
                return true;
            }

            if (! CronSetting::shouldRun($key)) {
                return false;
            }

            CronSetting::markRan($key);

            return true;
        } catch (\Throwable $e) {
            report($e);

            return true;
        }
    }
}
