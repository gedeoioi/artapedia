<?php

use App\Jobs\ExpireOverdueInvoices;
use App\Jobs\PollGatewayInvoice;
use App\Jobs\PollTransactionStatus;
use App\Jobs\SyncSupplierProducts;
use App\Models\CronSetting;
use App\Models\SupplierConfig;
use App\Models\Transaction;
use App\Support\CronGate;
use Illuminate\Support\Facades\Schedule;

// Polling status ke SUPPLIER untuk trx yang sedang diproses.
// Interval + on/off diatur di admin /admin/cron-settings.
// Idempoten: pollStatus() memakai lockForUpdate + cek isFinal, dan
// dispatchToSupplier() memakai atomic claim (supplier_trx_id) sehingga
// tidak terjadi double-order walau cron + worker berjalan bersamaan.
Schedule::call(function () {
    if (! CronGate::allows(CronSetting::KEY_POLL_PROCESSING)) {
        return;
    }
    Transaction::where('status', Transaction::STATUS_PROCESSING)
        ->where('created_at', '>', now()->subDay())
        ->orderBy('id')
        ->limit(50)
        ->each(fn ($trx) => PollTransactionStatus::dispatch($trx->id));
})->everyMinute()->name('poll-processing-transactions');

// Polling status PEMBAYARAN ke gateway untuk invoice pending.
// Backup kalau webhook telat/gagal sampai. Guard: markPaid() menolak
// memproses trx yang sudah final/paid/processing/expired (anti double saldo).
Schedule::call(function () {
    if (! CronGate::allows(CronSetting::KEY_POLL_GATEWAY)) {
        return;
    }
    Transaction::where('status', Transaction::STATUS_PENDING)
        ->where('payment_method', '!=', 'balance')
        ->whereNotNull('payment_reference')
        ->where('created_at', '>', now()->subDay())
        ->orderBy('id')
        ->limit(50)
        ->each(fn ($trx) => PollGatewayInvoice::dispatch($trx->id));
})->everyMinute()->name('poll-pending-gateway-invoices');

// Tandai expired untuk invoice yang lewat batas waktu.
// Guard: expireTransaction() menolak trx yang sudah paid/processing/final,
// dan markPaid() menolak trx expired — callback telat tidak bisa jadi paid.
Schedule::call(function () {
    if (! CronGate::allows(CronSetting::KEY_EXPIRE)) {
        return;
    }
    ExpireOverdueInvoices::dispatch();
})->everyMinute()->name('expire-overdue-invoices');

Schedule::call(function () {
    if (! CronGate::allows(CronSetting::KEY_SYNC_PRODUCTS)) {
        return;
    }
    SupplierConfig::where('is_active', true)->each(fn ($s) => SyncSupplierProducts::dispatch($s->id));
})->everyMinute()->name('sync-supplier-products');
