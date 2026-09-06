<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaction_id')->constrained()->cascadeOnDelete();
            $table->string('invoice_code')->unique();
            $table->string('gateway_code');
            $table->string('reference_id')->unique();
            $table->unsignedBigInteger('amount');
            $table->string('status')->default('pending');
            $table->timestamp('expired_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->json('payload')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
