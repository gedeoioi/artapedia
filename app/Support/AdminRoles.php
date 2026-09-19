<?php

namespace App\Support;

/**
 * Peran & izin panel admin.
 *
 * Dipusatkan di satu tempat supaya seeder, pengecekan akses resource, dan
 * tampilan form user tidak bisa berbeda pendapat soal nama izin.
 */
class AdminRoles
{
    public const SUPER_ADMIN = 'super-admin';

    public const ADMIN = 'admin';

    public const OPERATOR = 'operator';

    /** @var array<string, string> */
    public const LABELS = [
        self::SUPER_ADMIN => 'Super Admin',
        self::ADMIN => 'Admin',
        self::OPERATOR => 'Operator',
    ];

    public const PERM_USERS = 'manage users';

    public const PERM_PRODUCTS = 'manage products';

    public const PERM_SUPPLIERS = 'manage suppliers';

    public const PERM_GATEWAYS = 'manage gateways';

    public const PERM_SETTINGS = 'manage settings';

    public const PERM_REPORTS = 'view reports';

    public const PERM_TRANSACTIONS = 'manage transactions';

    public const PERM_MANUAL_ORDER = 'manual order';

    public const PERM_BALANCE = 'adjust balance';

    public const PERM_REVIEWS = 'moderate reviews';

    /** @var array<int, string> */
    public const PERMISSIONS = [
        self::PERM_USERS,
        self::PERM_PRODUCTS,
        self::PERM_SUPPLIERS,
        self::PERM_GATEWAYS,
        self::PERM_SETTINGS,
        self::PERM_REPORTS,
        self::PERM_TRANSACTIONS,
        self::PERM_MANUAL_ORDER,
        self::PERM_BALANCE,
        self::PERM_REVIEWS,
    ];

    /** Izin default per peran. Operator sengaja tidak bisa mengubah saldo. */
    public const ROLE_PERMISSIONS = [
        self::SUPER_ADMIN => self::PERMISSIONS,
        self::ADMIN => [
            self::PERM_PRODUCTS,
            self::PERM_SUPPLIERS,
            self::PERM_GATEWAYS,
            // Settings ikut Admin: yang membedakannya dari Super Admin hanya
            // kelola user, sesuai keterangan di form user.
            self::PERM_SETTINGS,
            self::PERM_REPORTS,
            self::PERM_TRANSACTIONS,
            self::PERM_MANUAL_ORDER,
            self::PERM_REVIEWS,
            self::PERM_BALANCE,
        ],
        self::OPERATOR => [
            self::PERM_TRANSACTIONS,
            self::PERM_MANUAL_ORDER,
            self::PERM_REVIEWS,
            // Operator tidak diberi akses laporan: isinya omzet & profit, dan
            // keterangan di form user menjanjikan "transaksi & review saja".
        ],
    ];

    /**
     * @return array<string, string> nama peran => label
     */
    public static function roleOptions(): array
    {
        return self::LABELS;
    }

    public static function isPanelRole(?string $name): bool
    {
        return $name !== null && array_key_exists($name, self::LABELS);
    }
}
