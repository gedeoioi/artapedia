<?php

use App\Payments\TripayGateway;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Baris gateway Tripay dikirim lewat migration, bukan hanya seeder:
     * database yang sudah berjalan tidak pernah menjalankan ulang seeder,
     * sehingga tanpa ini Tripay tidak akan pernah muncul di checkout
     * walaupun config dan class-nya sudah ada.
     */
    public function up(): void
    {
        if (! Schema::hasTable('payment_gateway_configs')) {
            return;
        }

        if (DB::table('payment_gateway_configs')->where('code', 'tripay')->exists()) {
            return;
        }

        // Tripay adalah default, jadi ia harus benar-benar urutan pertama di
        // checkout. Tanpa menggeser baris lain, sort_order 0 yang sama membuat
        // urutannya ditentukan database dan Tripay bisa muncul di mana saja.
        DB::table('payment_gateway_configs')->increment('sort_order');

        DB::table('payment_gateway_configs')->insert([
            'code' => 'tripay',
            'name' => 'Tripay',
            'gateway_class' => TripayGateway::class,
            'is_active' => false,
            'is_sandbox' => true,
            'sort_order' => 0,
            // Kolom ini di-cast `encrypted:array`, jadi nilainya HARUS ditulis
            // lewat model (yang mengenkripsi) atau dibiarkan null. Menulis JSON
            // mentah di sini membuat cast gagal didekripsi dan setiap pembacaan
            // konfigurasi gateway meledak dengan DecryptException.
            'credentials' => null,
            'fee_flat' => 0,
            'fee_percent' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('payment_gateway_configs')) {
            return;
        }

        DB::table('payment_gateway_configs')->where('code', 'tripay')->delete();
    }
};
