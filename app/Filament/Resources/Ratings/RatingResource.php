<?php

namespace App\Filament\Resources\Ratings;

use App\Filament\Resources\Ratings\Pages\ListRatings;
use App\Filament\Resources\Ratings\Tables\RatingsTable;
use App\Support\AdminRoles;
use App\Models\Rating;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class RatingResource extends Resource
{
    protected static ?string $model = Rating::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedStar;

    protected static ?string $navigationLabel = 'Rating & Review';

    protected static string|\UnitEnum|null $navigationGroup = 'Transaksi';

    protected static ?string $modelLabel = 'Rating';

    public static function table(Table $table): Table
    {
        return RatingsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRatings::route('/'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        $pending = Rating::where('status', Rating::STATUS_PENDING)->count();

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
        return auth()->user()?->hasAdminPermission(AdminRoles::PERM_REVIEWS) ?? false;
    }
}
