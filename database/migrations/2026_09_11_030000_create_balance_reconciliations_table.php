<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('balance_reconciliations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->bigInteger('ledger_total');
            $table->bigInteger('cached_balance');
            $table->bigInteger('difference');
            // Jumlah transaksi berstatus paid/processing/success yang tidak punya
            // satu pun baris ledger. Cache dan ledger bisa sama-sama setuju pada
            // kasus ini, jadi perbandingan saldo saja tidak akan melihatnya.
            $table->unsignedInteger('unbacked_transactions')->default(0);
            $table->string('action')->default('none');
            $table->boolean('alerted')->default(false);
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('balance_reconciliations');
    }
};
