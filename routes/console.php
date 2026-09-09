<?php

use App\Jobs\ExpireOverdueInvoices;
use App\Jobs\PollGatewayInvoice;
use App\Jobs\SyncSupplierProducts;
use App\Models\CronSetting;
use App\Models\SupplierConfig;
use App\Models\Transaction;
use App\Services\OrderService;
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
    // Jalankan langsung dari scheduler. Sebelumnya hanya dispatch ke queue;
    // bila worker memakai koneksi/config berbeda, job terlihat habis tetapi
    // status tidak pernah tersinkron. pollStatus() idempoten dan memakai lock.
    $orders = app(OrderService::class);

    // Pulihkan juga data lama dari implementasi sebelumnya yang hanya menulis
    // supplier_status tanpa memfinalkan transaksi.
    Transaction::where('status', Transaction::STATUS_PROCESSING)
        ->where('supplier_status', 'all_suppliers_failed')
        ->orderBy('id')
        ->limit(50)
        ->each(fn ($trx) => $orders->markAllSuppliersFailed($trx->id));

    Transaction::where('status', Transaction::STATUS_PROCESSING)
        ->whereNotNull('supplier_trx_id')
        ->where('created_at', '>', now()->subDays(7))
        ->orderBy('id')
        ->limit(50)
        ->each(function ($trx) use ($orders) {
            try {
                $orders->pollStatus($trx->id);
            } catch (Throwable $e) {
                report($e);
            }
        });
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
