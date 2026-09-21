<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Urutan tampil kategori di beranda.
 *
 * Sebelumnya grid "Semua Kategori" selalu urut abjad, sehingga admin tidak
 * punya cara menaruh kategori penting di depan. Kolom ini mengisi urutan per
 * baris game_icons — baris yang dibuat otomatis saat sync produk, jadi setiap
 * kategori yang punya produk sudah punya tempat untuk menyimpan urutannya.
 *
 * Baris lama diisi berurutan menurut abjad supaya tampilan beranda tidak
 * berubah setelah migrasi ini dijalankan.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('game_icons', 'display_order')) {
            Schema::table('game_icons', function (Blueprint $table) {
                $table->smallInteger('display_order')->unsigned()->default(0)->after('favorite_order');
            });
        }

        // Isi urutan awal dari abjad, hanya untuk baris yang masih 0, supaya
        // migrasi tidak menimpa urutan yang sudah diatur admin.
        $rows = DB::table('game_icons')
            ->where('display_order', 0)
            ->orderBy('game_name')
            ->pluck('id');

        foreach ($rows as $index => $id) {
            DB::table('game_icons')->where('id', $id)->update(['display_order' => $index + 1]);
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('game_icons', 'display_order')) {
            Schema::table('game_icons', function (Blueprint $table) {
                $table->dropColumn('display_order');
            });
        }
    }
};
