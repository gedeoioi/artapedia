<?php

namespace App\Http\Controllers;

use App\Models\Transaction;

class PaymentController extends Controller
{
    public function show(string $invoice)
    {
        $trx = Transaction::with(['product', 'invoice'])->where('invoice_code', $invoice)->firstOrFail();

        return view('payment', compact('trx'));
    }

    public function status(string $invoice)
    {
        $trx = Transaction::where('invoice_code', $invoice)->firstOrFail();

        return response()->json(['status' => $trx->status, 'paid_at' => $trx->paid_at]);
    }
}
