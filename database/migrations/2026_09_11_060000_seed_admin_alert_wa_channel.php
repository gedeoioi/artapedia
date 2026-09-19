<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Channel alert admin dikirim sebagai migration, bukan perubahan seeder:
     * database yang sudah berjalan tidak pernah menjalankan ulang seeder, dan
     * seeder saja akan membuat fitur alert diam-diam tidak pernah aktif.
     */
    public function up(): void
    {
        if (! Schema::hasTable('wa_notification_settings')) {
            return;
        }

        DB::table('wa_notification_settings')->updateOrInsert(
            ['name' => 'admin_alert'],
            [
                'is_active' => false,
                'recipient' => null,
                'template' => '{message}',
                'schedule' => 'on_event',
                'api_url' => null,
                'api_token' => null,
                'updated_at' => now(),
                'created_at' => now(),
            ],
        );
    }

    public function down(): void
    {
        if (! Schema::hasTable('wa_notification_settings')) {
            return;
        }

        DB::table('wa_notification_settings')->where('name', 'admin_alert')->delete();
    }
};
