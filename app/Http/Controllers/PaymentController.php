<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use App\Services\SupplierStatusSynchronizer;

class PaymentController extends Controller
{
    public function show(string $invoice)
    {
        $trx = Transaction::with(['product', 'invoice'])->where('invoice_code', $invoice)->firstOrFail();

        return view('payment', compact('trx'));
    }

    public function status(string $invoice, SupplierStatusSynchronizer $synchronizer)
    {
        $trx = Transaction::where('invoice_code', $invoice)->firstOrFail();
        $trx = $synchronizer->refreshIfDue($trx);

        return response()->json([
            'status' => $trx->status,
            'status_label' => $trx->statusLabel(),
            'message' => $trx->statusMessage(),
            'badge' => $trx->statusBadgeClass(),
            'supplier_status' => $trx->supplier_status,
            'paid_at' => $trx->paid_at,
            'processed_at' => $trx->processed_at,
        ])->header('Cache-Control', 'no-store, no-cache, must-revalidate');
    }
}
