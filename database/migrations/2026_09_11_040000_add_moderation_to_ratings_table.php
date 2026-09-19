<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ratings', function (Blueprint $table) {
            // pending = menunggu moderasi admin, approved = tampil publik,
            // hidden = disembunyikan admin.
            $table->string('status')->default('pending');
            $table->string('moderation_note')->nullable();
            $table->unique('transaction_id');
        });
    }

    public function down(): void
    {
        Schema::table('ratings', function (Blueprint $table) {
            $table->dropUnique(['transaction_id']);
            $table->dropColumn(['status', 'moderation_note']);
        });
    }
};
