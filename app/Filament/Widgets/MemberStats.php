<?php

namespace App\Filament\Widgets;

use App\Models\User;
use App\Support\Rupiah;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class MemberStats extends StatsOverviewWidget
{
    protected static ?int $sort = 2;

    protected function getStats(): array
    {
        $members = User::query()
            ->where('level', '!=', 'admin')
            ->whereDoesntHave('roles', fn ($query) => $query->where('name', 'admin'));

        return [
            Stat::make('Jumlah Member', (clone $members)->count())
                ->description('Semua member, reseller biasa, dan VIP'),
            Stat::make('Total Saldo Member', Rupiah::format((clone $members)->sum('balance')))
                ->description('Total saldo tersimpan milik seluruh member'),
        ];
    }
}
