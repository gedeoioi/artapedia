<?php

namespace App\Services;

use App\Contracts\PaymentGatewayInterface;
use App\Contracts\SupplierProviderInterface;
use App\Models\PaymentGatewayConfig;
use App\Models\SupplierConfig;
use App\Payments\DuitkuGateway;
use App\Payments\IPaymuGateway;
use App\Payments\XenditGateway;
use App\Suppliers\DigiflazzProvider;
use App\Suppliers\TokoVoucherProvider;
use App\Suppliers\VipResellerProvider;

class ProviderFactory
{
    public static function supplierFor(SupplierConfig $config): SupplierProviderInterface
    {
        $map = config('artapedia.suppliers', []);
        $class = $map[$config->code] ?? $config->provider_class;

        return new $class($config->credentials ?? [], (bool) $config->is_sandbox);
    }

    public static function supplierByCode(string $code): ?SupplierProviderInterface
    {
        $config = SupplierConfig::where('code', $code)->where('is_active', true)->first();
        if (! $config) {
            return null;
        }

        return static::supplierFor($config);
    }

    public static function gatewayFor(PaymentGatewayConfig $config): PaymentGatewayInterface
    {
        $map = config('artapedia.gateways', []);
        $class = $map[$config->code] ?? $config->gateway_class;

        return new $class($config->credentials ?? [], (bool) $config->is_sandbox);
    }

    public static function gatewayByCode(string $code): ?PaymentGatewayInterface
    {
        $config = PaymentGatewayConfig::where('code', $code)->where('is_active', true)->first();
        if (! $config) {
            return null;
        }

        return static::gatewayFor($config);
    }

    public static function defaultSupplierMap(): array
    {
        return [
            'vip-reseller' => VipResellerProvider::class,
            'digiflazz' => DigiflazzProvider::class,
            'toko-voucher' => TokoVoucherProvider::class,
        ];
    }

    public static function defaultGatewayMap(): array
    {
        return [
            'xendit' => XenditGateway::class,
            'duitku' => DuitkuGateway::class,
            'ipaymu' => IPaymuGateway::class,
        ];
    }
}
