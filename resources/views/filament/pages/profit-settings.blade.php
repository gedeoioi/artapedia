<x-filament-panels::page>
    <div class="space-y-4">
        <form wire:submit="save">
            {{ $this->form }}
        </form>
        <div class="rounded-xl bg-gray-50 p-4 text-sm text-gray-600 dark:bg-white/5 dark:text-gray-400">
            Pengaturan tersimpan akan dipakai otomatis saat menarik produk dari supplier.
            Gunakan <strong>Simpan &amp; Terapkan ke Semua Produk</strong> hanya jika ingin menghitung ulang harga produk yang sudah ada.
        </div>
    </div>
</x-filament-panels::page>
