<?php

namespace App\Contracts;

interface PaymentGatewayInterface
{
    public function code(): string;

    public function createPayment(array $params): array;

    public function handleCallback(array $payload, array $headers = []): array;

    public function checkStatus(string $referenceId): array;
}
