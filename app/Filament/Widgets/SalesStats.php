<?php

namespace App\Filament\Widgets;

use App\Models\Transaction;
use App\Support\Rupiah;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class SalesStats extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $today = Transaction::whereNotNull('product_id')->whereDate('created_at', today());
        $omzet = (clone $today)->where('status', 'success')->sum('total_amount');
        $profit = (clone $today)->where('status', 'success')->sum('profit');

        return [
            Stat::make('Omzet produk hari ini', Rupiah::format($omzet)),
            Stat::make('Profit produk hari ini', Rupiah::format($profit)),
            Stat::make('Transaksi produk sukses', (clone $today)->where('status', 'success')->count()),
            Stat::make('Produk pending / diproses', (clone $today)->whereIn('status', ['pending', 'paid', 'processing'])->count()),
        ];
    }
}
