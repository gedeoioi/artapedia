<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_config_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('game_icon_id')->nullable()->constrained('game_icons')->nullOnDelete();
            $table->string('supplier_code');
            $table->string('name');
            $table->string('game')->index();
            $table->string('category')->default('game');
            $table->string('nickname_check_code')->nullable();
            $table->unsignedBigInteger('cost_basic')->default(0);
            $table->unsignedBigInteger('cost_premium')->default(0);
            $table->unsignedBigInteger('cost_special')->default(0);
            $table->unsignedBigInteger('price_guest')->default(0);
            $table->unsignedBigInteger('price_biasa')->default(0);
            $table->unsignedBigInteger('price_vip')->default(0);
            $table->boolean('is_active')->default(true);
            $table->boolean('in_stock')->default(true);
            $table->string('image_path')->nullable();
            $table->text('description')->nullable();
            $table->string('meta_title')->nullable();
            $table->text('meta_description')->nullable();
            $table->timestamps();
            $table->unique(['supplier_config_id', 'supplier_code']);
            $table->index('supplier_code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
