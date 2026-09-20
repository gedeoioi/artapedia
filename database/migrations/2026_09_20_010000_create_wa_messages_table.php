<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kolom api_token sudah ada di tabel wa_notification_settings dan dipakai kode,
 * tapi belum bisa diisi dari admin. Tabel wa_messages juga belum ada, sehingga
 * tidak ada cara mengetahui sebuah pesan terkirim atau tidak.
 *
 * Migrasi ini juga memindahkan token dari kolom ke daftar setting supaya
 * operator bisa mengisinya dari panel (api_url juga diisi dari sana).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('wa_messages')) {
            Schema::create('wa_messages', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('transaction_id')->nullable()->constrained()->nullOnDelete();
                $table->string('kind');                 // trx_status, daily_recap, admin_alert
                $table->string('phone');
                $table->text('message');
                $table->string('status')->default('pending'); // pending|sent|failed
                $table->unsignedInteger('attempts')->default(0);
                $table->unsignedSmallInteger('http_status')->nullable();
                $table->text('response')->nullable();
                $table->text('error')->nullable();
                $table->timestamp('sent_at')->nullable();
                $table->timestamps();

                // Satu baris per (transaksi, jenis pesan): percobaan ulang menambah
                // attempts, bukan menumpuk baris duplikat.
                $table->unique(['transaction_id', 'kind']);
                $table->index(['status', 'created_at']);
                $table->index('phone');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('wa_messages');
    }
};
