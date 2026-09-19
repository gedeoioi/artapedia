<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tugas rekonsiliasi dikirim sebagai migration, bukan lewat seeder.
     * CronSetting::shouldRun() menolak key yang belum punya baris, jadi seeder
     * saja tidak akan pernah sampai ke database yang sudah berjalan —
     * schedule:list tetap menampilkannya sementara gate-nya selamanya false.
     */
    public function up(): void
    {
        if (! Schema::hasTable('cron_settings')) {
            return;
        }

        DB::table('cron_settings')->updateOrInsert(
            ['key' => 'reconcile-balances'],
            [
                'label' => 'Rekonsiliasi ledger vs saldo user',
                'interval_minutes' => 1440,
                'is_active' => true,
                'updated_at' => now(),
                'created_at' => now(),
            ],
        );
    }

    public function down(): void
    {
        if (! Schema::hasTable('cron_settings')) {
            return;
        }

        DB::table('cron_settings')->where('key', 'reconcile-balances')->delete();
    }
};
