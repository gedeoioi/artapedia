<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('balance')->default(0)->after('password');
            $table->string('level')->default('member')->after('balance');
            $table->string('status')->default('active')->after('level');
            $table->string('phone')->nullable()->after('status');
            $table->string('whatsapp')->nullable()->after('phone');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['balance', 'level', 'status', 'phone', 'whatsapp']);
        });
    }
};
