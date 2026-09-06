<?php

use App\Jobs\PollTransactionStatus;
use App\Models\SupplierConfig;
use App\Models\Transaction;
use App\Jobs\SyncSupplierProducts;
use Illuminate\Support\Facades\Schedule;

Schedule::call(function () {
    Transaction::where('status', Transaction::STATUS_PROCESSING)
        ->where('created_at', '>', now()->subDay())
        ->orderBy('id')
        ->limit(50)
        ->each(fn ($trx) => PollTransactionStatus::dispatch($trx->id));
})->everyFiveMinutes()->name('poll-processing-transactions');

Schedule::call(function () {
    SupplierConfig::where('is_active', true)->each(fn ($s) => SyncSupplierProducts::dispatch($s->id));
})->hourly()->name('sync-supplier-products');
