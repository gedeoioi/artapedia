<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('supplier_configs', function (Blueprint $table) {
            $table->timestamp('last_test_at')->nullable()->after('last_sync_at');
            $table->boolean('last_test_ok')->nullable()->after('last_test_at');
            $table->string('last_test_summary')->nullable()->after('last_test_ok');
            $table->text('last_test_log')->nullable()->after('last_test_summary');
        });
    }

    public function down(): void
    {
        Schema::table('supplier_configs', function (Blueprint $table) {
            $table->dropColumn(['last_test_at', 'last_test_ok', 'last_test_summary', 'last_test_log']);
        });
    }
};
