<?php

namespace App\Contracts;

interface PaymentGatewayInterface
{
    public function code(): string;

    public function createPayment(array $params): array;

    /**
     * $rawBody adalah isi request mentah (belum di-parse).
     *
     * Dibutuhkan karena sebagian gateway (mis. Tripay) menandatangani body JSON
     * apa adanya. Menyusun ulang JSON dari array tidak dijamin byte-identik
     * (urutan key, spasi, escaping), sehingga verifikasi signature bisa gagal
     * untuk callback yang sebenarnya sah.
     */
    public function handleCallback(array $payload, array $headers = [], ?string $rawBody = null): array;

    public function checkStatus(string $referenceId): array;
}
