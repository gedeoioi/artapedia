<?php

namespace App\Http\Controllers;

use App\Contracts\NicknameCheckableInterface;
use App\Models\PaymentGatewayConfig;
use App\Models\Product;
use App\Services\NicknameService;
use App\Services\OrderService;
use App\Services\PaymentService;
use App\Services\ProviderFactory;
use Illuminate\Http\Request;

class CheckoutController extends Controller
{
    public function show(Product $product)
    {
        abort_unless($product->is_active && $product->in_stock, 404);

        $gateways = PaymentGatewayConfig::activeOrdered();

        return view('checkout', compact('product', 'gateways'));
    }

    public function quote(Request $request, PaymentService $payments)
    {
        $product = Product::available()->findOrFail($request->get('product_id'));

        return response()->json($payments->quote($product, $request->user(), $request->get('gateway_code', 'balance')));
    }

    public function checkNickname(Request $request, NicknameService $nicknames)
    {
        $data = $request->validate([
            'product_id' => 'required|exists:products,id',
            'user_id' => 'required|string|max:64',
            'zone_id' => 'nullable|string|max:32',
        ]);

        $product = Product::available()->findOrFail($data['product_id']);

        // Cek nickname SELALU via VIPayment, terlepas supplier asal produk.
        // (Hanya VIP yang punya endpoint get-nickname.)
        $result = $nicknames->check($product, $data['user_id'], $data['zone_id'] ?? null);

        if (! ($result['ok'] ?? false)) {
            return response()->json(['ok' => false, 'message' => $result['message'] ?? 'Nickname tidak ditemukan.'], 422);
        }

        return response()->json($result);
    }

    public function store(Request $request, OrderService $orders)
    {
        $data = $request->validate([
            'product_id' => 'required|exists:products,id',
            'target_user_id' => 'required|string|max:64',
            'target_zone' => 'nullable|string|max:32',
            'nickname' => 'nullable|string|max:128',
            'quantity' => 'nullable|integer|min:1|max:100',
            'gateway_code' => 'required|string',
            'buyer_phone' => 'nullable|string|max:32',
            'buyer_email' => 'nullable|email|max:128',
        ]);

        try {
            $trx = $orders->checkout($data, $request->user());
        } catch (\Throwable $e) {
            return back()->withErrors(['checkout' => $e->getMessage()])->withInput();
        }

        return redirect()->route('payment.show', $trx->invoice_code);
    }
}
