<x-filament-panels::page>
    <div class="space-y-4">
        {{ $this->form }}
        <div class="text-sm text-gray-500">
            Pilih supplier &rarr; pilih jenis produk (Game, Pulsa Reguler, atau Paket Internet) &rarr; pilih kategori/operator (atau kosongkan untuk SEMUA) &rarr; klik <b>Tarik Sekarang</b>.
            Tombol <b>Hapus Produk</b> menghapus produk pada cakupan terpilih agar bisa tarik ulang dari nol (produk bertransaksi hanya dinonaktifkan).
        </div>
        {{ $this->table }}
    </div>
</x-filament-panels::page>
