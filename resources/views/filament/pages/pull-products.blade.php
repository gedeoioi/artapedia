<x-filament-panels::page>
    <div class="space-y-4">
        {{ $this->form }}
        <div class="text-sm text-gray-500">Kosongkan filter game untuk menarik SEMUA produk kategori itu. Hasil sync tampil di tabel bawah (harga per tier + status stok).</div>
        {{ $this->table }}
    </div>
</x-filament-panels::page>
