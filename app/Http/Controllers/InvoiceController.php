<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use App\Services\SupplierStatusSynchronizer;
use Illuminate\Http\Request;

class InvoiceController extends Controller
{
    public function index()
    {
        return view('invoice-check');
    }

    public function show(Request $request, SupplierStatusSynchronizer $synchronizer)
    {
        $code = trim((string) $request->get('code', ''));
        $trx = $code ? Transaction::with(['product', 'rating', 'invoice'])->where('invoice_code', $code)->first() : null;
        if ($trx) {
            $trx = $synchronizer->refreshIfDue($trx);
            $trx->loadMissing(['product', 'rating']);
        }

        return view('invoice-check', compact('trx', 'code'));
    }

    public function api(string $code, SupplierStatusSynchronizer $synchronizer)
    {
        $trx = Transaction::where('invoice_code', $code)->firstOrFail();
        $trx = $synchronizer->refreshIfDue($trx);

        return response()->json([
            'invoice' => $trx->invoice_code,
            'status' => $trx->status,
            'product' => $trx->product->name ?? null,
            'total' => $trx->total_amount,
            'paid_at' => $trx->paid_at,
        ]);
    }
}
