@php
    $cards = [
        ['Jumlah transaksi', number_format($summary['count'], 0, ',', '.')],
        ['Omzet', $rupiah($summary['revenue'])],
        ['Total modal', $rupiah($summary['cost'])],
        ['Fee gateway', $rupiah($summary['gateway_fee'])],
        ['Profit bersih', $rupiah($summary['profit'])],
        ['Rata-rata transaksi', $rupiah($summary['average'])],
    ];
@endphp

<x-filament-panels::page>
    {{ $this->form }}

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        @foreach($cards as [$label, $value])
            <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-white/10 dark:bg-white/5">
                <div class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ $label }}</div>
                <div class="mt-1 text-xl font-bold text-gray-950 dark:text-white">{{ $value }}</div>
            </div>
        @endforeach
    </div>

    <p class="text-xs text-gray-500 dark:text-gray-400">
        Rentang {{ $from->format('d/m/Y') }} – {{ $to->format('d/m/Y') }}.
        Profit bersih = omzet − modal − fee gateway yang ditanggung merchant.
        Hanya transaksi berstatus sukses yang dihitung.
    </p>

    <div class="rounded-xl border border-gray-200 bg-white dark:border-white/10 dark:bg-white/5">
        <div class="border-b border-gray-200 px-4 py-3 font-semibold text-gray-950 dark:border-white/10 dark:text-white">
            {{ $dimensionLabel }}
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-500 dark:bg-white/5 dark:text-gray-400">
                    <tr>
                        <th class="px-4 py-2 text-left">Label</th>
                        <th class="px-4 py-2 text-right">Transaksi</th>
                        <th class="px-4 py-2 text-right">Omzet</th>
                        <th class="px-4 py-2 text-right">Modal</th>
                        <th class="px-4 py-2 text-right">Profit</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($breakdown as $row)
                        <tr class="border-t border-gray-100 dark:border-white/5">
                            <td class="px-4 py-2 text-gray-950 dark:text-white">{{ $row->label }}</td>
                            <td class="px-4 py-2 text-right">{{ number_format($row->trx_count, 0, ',', '.') }}</td>
                            <td class="px-4 py-2 text-right">{{ $rupiah($row->revenue) }}</td>
                            <td class="px-4 py-2 text-right">{{ $rupiah($row->cost) }}</td>
                            <td class="px-4 py-2 text-right font-semibold">{{ $rupiah($row->profit) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-6 text-center text-gray-500 dark:text-gray-400">
                                Tidak ada transaksi sukses pada rentang tanggal ini.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="rounded-xl border border-gray-200 bg-white dark:border-white/10 dark:bg-white/5">
        <div class="border-b border-gray-200 px-4 py-3 font-semibold text-gray-950 dark:border-white/10 dark:text-white">
            Rekap harian
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-500 dark:bg-white/5 dark:text-gray-400">
                    <tr>
                        <th class="px-4 py-2 text-left">Tanggal</th>
                        <th class="px-4 py-2 text-right">Transaksi</th>
                        <th class="px-4 py-2 text-right">Omzet</th>
                        <th class="px-4 py-2 text-right">Profit</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($daily as $row)
                        <tr class="border-t border-gray-100 dark:border-white/5">
                            <td class="px-4 py-2 text-gray-950 dark:text-white">{{ $row->day }}</td>
                            <td class="px-4 py-2 text-right">{{ number_format($row->trx_count, 0, ',', '.') }}</td>
                            <td class="px-4 py-2 text-right">{{ $rupiah($row->revenue) }}</td>
                            <td class="px-4 py-2 text-right font-semibold">{{ $rupiah($row->profit) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-4 py-6 text-center text-gray-500 dark:text-gray-400">Belum ada data.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-filament-panels::page>
