<?php

namespace App\Http\Controllers;

use App\Models\PaymentGatewayConfig;
use App\Services\PaymentService;
use Illuminate\Http\Request;

class TopupController extends Controller
{
    public function create()
    {
        $gateways = PaymentGatewayConfig::activeOrdered();

        return view('member.topup', compact('gateways'));
    }

    public function store(Request $request, PaymentService $payments)
    {
        $data = $request->validate([
            'amount' => 'required|integer|min:10000|max:10000000',
            'gateway_code' => 'required|string|exists:payment_gateway_configs,code',
        ]);

        $trx = $payments->topupBalance($request->user(), $data['amount'], $data['gateway_code']);

        return redirect()->route('payment.show', $trx->invoice_code);
    }
}
