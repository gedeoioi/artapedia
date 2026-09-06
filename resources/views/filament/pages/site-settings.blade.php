<x-filament-panels::page>
    <div class="space-y-4">
        <form wire:submit="save">
            {{ $this->form }}
        </form>
        <p class="text-sm text-gray-500">Klik "Simpan Pengaturan" di kanan atas. Logo tampil di header toko, favicon di tab browser, deskripsi dipakai sebagai meta description SEO.</p>
    </div>
</x-filament-panels::page>
