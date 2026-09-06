<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use Illuminate\Http\Request;

class InvoiceController extends Controller
{
    public function index()
    {
        return view('invoice-check');
    }

    public function show(Request $request)
    {
        $code = trim((string) $request->get('code', ''));
        $trx = $code ? Transaction::with(['product', 'rating'])->where('invoice_code', $code)->first() : null;

        return view('invoice-check', compact('trx', 'code'));
    }

    public function api(string $code)
    {
        $trx = Transaction::where('invoice_code', $code)->firstOrFail();

        return response()->json([
            'invoice' => $trx->invoice_code,
            'status' => $trx->status,
            'product' => $trx->product->name ?? null,
            'total' => $trx->total_amount,
            'paid_at' => $trx->paid_at,
        ]);
    }
}
