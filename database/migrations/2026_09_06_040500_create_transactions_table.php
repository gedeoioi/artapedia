<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_code')->unique();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('supplier_config_id')->nullable()->constrained()->nullOnDelete();
            $table->string('payment_gateway_code')->default('balance');
            $table->string('target_user_id');
            $table->string('target_zone')->nullable();
            $table->string('nickname')->nullable();
            $table->unsignedInteger('quantity')->default(1);
            $table->unsignedBigInteger('cost_price')->default(0);
            $table->unsignedBigInteger('sell_price')->default(0);
            $table->unsignedBigInteger('admin_fee')->default(0);
            $table->unsignedBigInteger('gateway_fee')->default(0);
            $table->unsignedBigInteger('total_amount')->default(0);
            $table->bigInteger('profit')->default(0);
            $table->string('payment_method')->default('balance');
            $table->string('payment_reference')->nullable()->unique();
            $table->text('payment_payload')->nullable();
            $table->string('status')->default('pending');
            $table->string('supplier_trx_id')->nullable();
            $table->string('supplier_status')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->string('buyer_phone')->nullable();
            $table->string('buyer_email')->nullable();
            $table->text('notes')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->index(['status', 'created_at']);
            $table->index('supplier_trx_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
