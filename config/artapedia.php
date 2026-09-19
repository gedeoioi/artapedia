<?php

use App\Payments\DuitkuGateway;
use App\Payments\IPaymuGateway;
use App\Payments\TripayGateway;
use App\Payments\XenditGateway;
use App\Suppliers\DigiflazzProvider;
use App\Suppliers\TokoVoucherProvider;
use App\Suppliers\VipResellerProvider;

return [
    'initial_admin' => [
        'email' => env('INITIAL_ADMIN_EMAIL', 'admin@artapedia.id'),
        'password' => env('INITIAL_ADMIN_PASSWORD', ''),
    ],

    'suppliers' => [
        'vip-reseller' => VipResellerProvider::class,
        'digiflazz' => DigiflazzProvider::class,
        'toko-voucher' => TokoVoucherProvider::class,
    ],
    'gateways' => [
        // Tripay didaftarkan lebih dulu sebagai default (paling ramah untuk
        // usaha baru: onboarding cukup KTP) dan sort_order 0 di seeder.
        'tripay' => TripayGateway::class,
        'xendit' => XenditGateway::class,
        'duitku' => DuitkuGateway::class,
        'ipaymu' => IPaymuGateway::class,
    ],
];
