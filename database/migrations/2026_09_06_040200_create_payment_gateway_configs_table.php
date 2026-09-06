<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_gateway_configs', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('gateway_class');
            $table->boolean('is_active')->default(true);
            $table->boolean('is_sandbox')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->text('credentials')->nullable();
            $table->unsignedBigInteger('fee_flat')->default(0);
            $table->decimal('fee_percent', 5, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_gateway_configs');
    }
};
