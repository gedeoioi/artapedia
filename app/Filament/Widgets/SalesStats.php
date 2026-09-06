<?php

namespace App\Filament\Widgets;

use App\Models\Transaction;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class SalesStats extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        $today = Transaction::whereDate('created_at', today());
        $omzet = (clone $today)->where('status', 'success')->sum('total_amount');
        $profit = (clone $today)->where('status', 'success')->sum('profit');

        return [
            Stat::make('Omzet hari ini', 'Rp '.number_format($omzet, 0, ',', '.')),
            Stat::make('Profit hari ini', 'Rp '.number_format($profit, 0, ',', '.')),
            Stat::make('Transaksi sukses', (clone $today)->where('status', 'success')->count()),
            Stat::make('Pending / diproses', (clone $today)->whereIn('status', ['pending', 'paid', 'processing'])->count()),
        ];
    }
}
