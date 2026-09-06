<?php

return [
    'suppliers' => [
        'vip-reseller' => App\Suppliers\VipResellerProvider::class,
        'digiflazz' => App\Suppliers\DigiflazzProvider::class,
        'toko-voucher' => App\Suppliers\TokoVoucherProvider::class,
    ],
    'gateways' => [
        'xendit' => App\Payments\XenditGateway::class,
        'duitku' => App\Payments\DuitkuGateway::class,
        'ipaymu' => App\Payments\IPaymuGateway::class,
    ],
];
