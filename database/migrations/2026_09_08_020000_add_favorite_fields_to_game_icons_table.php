<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('game_icons', function (Blueprint $table) {
            $table->boolean('is_favorite')->default(false)->after('is_active')->index();
            $table->unsignedSmallInteger('favorite_order')->default(0)->after('is_favorite');
        });
    }

    public function down(): void
    {
        Schema::table('game_icons', function (Blueprint $table) {
            $table->dropIndex(['is_favorite']);
            $table->dropColumn(['is_favorite', 'favorite_order']);
        });
    }
};
