<?php

namespace App\Suppliers;

use App\Contracts\SupplierProviderInterface;

abstract class BaseSupplierStub implements SupplierProviderInterface
{
    public function __construct(protected array $credentials = [], protected bool $sandbox = true) {}

    public function getBalance(): array
    {
        return ['result' => true, 'data' => ['balance' => 0], 'note' => 'stub'];
    }

    public function getProducts(array $filters = []): array
    {
        return ['result' => true, 'data' => []];
    }

    public function order(string $productCode, string $target, array $options = []): array
    {
        return ['result' => false, 'message' => 'Provider stub: order manual via admin.'];
    }

    public function checkStatus(string $supplierTrxId): array
    {
        return ['result' => false, 'message' => 'Provider stub.'];
    }
}
