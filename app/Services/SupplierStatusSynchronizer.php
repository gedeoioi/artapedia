<?php

namespace App\Services;

use App\Models\Transaction;
use Illuminate\Support\Facades\Cache;

class SupplierStatusSynchronizer
{
    public function __construct(protected OrderService $orders) {}

    /**
     * Fallback polling ketika invoice dibuka. Dibatasi agar refresh browser
     * tidak membanjiri API supplier.
     */
    public function refreshIfDue(Transaction $transaction, int $seconds = 15): Transaction
    {
        if ($transaction->status !== Transaction::STATUS_PROCESSING || ! $transaction->supplier_trx_id) {
            return $transaction;
        }

        $key = 'supplier-status-poll:'.$transaction->id;
        if (! Cache::add($key, true, now()->addSeconds($seconds))) {
            return $transaction->fresh();
        }

        try {
            return $this->orders->pollStatus($transaction->id);
        } catch (\Throwable $e) {
            report($e);

            return $transaction->fresh();
        }
    }
}
