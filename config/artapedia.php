<?php

use App\Payments\DuitkuGateway;
use App\Payments\IPaymuGateway;
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
        'xendit' => XenditGateway::class,
        'duitku' => DuitkuGateway::class,
        'ipaymu' => IPaymuGateway::class,
    ],
];
