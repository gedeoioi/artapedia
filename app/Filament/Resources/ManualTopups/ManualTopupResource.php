<?php

namespace App\Filament\Resources\ManualTopups;

use App\Filament\Resources\ManualTopups\Pages\ListManualTopups;
use App\Filament\Resources\ManualTopups\Tables\ManualTopupsTable;
use App\Models\ManualTopup;
use App\Support\AdminRoles;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ManualTopupResource extends Resource
{
    protected static ?string $model = ManualTopup::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static ?string $navigationLabel = 'Topup Manual';

    protected static string|\UnitEnum|null $navigationGroup = 'Transaksi';

    protected static ?string $modelLabel = 'Topup Manual';

    public static function table(Table $table): Table
    {
        return ManualTopupsTable::configure($table);
    }

    /**
     * Topup manual dibuat dari sisi member (unggah bukti), bukan diketik admin.
     * Jadi hanya ada layar daftar + review; form create/edit sengaja tidak ada
     * supaya tidak ada jalur kedua yang bisa mengkredit saldo.
     */
    public static function getPages(): array
    {
        return [
            'index' => ListManualTopups::route('/'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        $pending = ManualTopup::where('status', ManualTopup::STATUS_PENDING)->count();

        return $pending > 0 ? (string) $pending : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    /**
     * Akses layar ini dibatasi izin, bukan hanya "punya peran admin".
     * Operator tidak boleh membuka layar yang bisa mengubah uang/konfigurasi.
     */
    public static function canViewAny(): bool
    {
        return auth()->user()?->hasAdminPermission(AdminRoles::PERM_TRANSACTIONS) ?? false;
    }
}
