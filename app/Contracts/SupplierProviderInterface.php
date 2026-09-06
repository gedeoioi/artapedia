<?php

namespace App\Contracts;

interface SupplierProviderInterface
{
    public function code(): string;

    public function getBalance(): array;

    public function getProducts(array $filters = []): array;

    public function order(string $productCode, string $target, array $options = []): array;

    public function checkStatus(string $supplierTrxId): array;
}
