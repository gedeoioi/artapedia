<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_configs', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('provider_class');
            $table->boolean('is_active')->default(true);
            $table->boolean('is_sandbox')->default(true);
            $table->unsignedInteger('priority')->default(0);
            $table->text('credentials')->nullable();
            $table->unsignedBigInteger('cached_balance')->default(0);
            $table->timestamp('last_sync_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_configs');
    }
};
