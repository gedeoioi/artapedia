<?php

namespace App\Http\Controllers;

use App\Models\PaymentGatewayConfig;
use App\Models\Product;
use App\Payments\IPaymuGateway;
use App\Services\NicknameService;
use App\Services\OrderService;
use App\Services\PaymentService;
use Illuminate\Http\Request;

class CheckoutController extends Controller
{
    public function show(Product $product)
    {
        abort_unless($product->is_active && $product->in_stock, 404);

        $product->loadMissing('supplier');
        $gateways = PaymentGatewayConfig::activeOrdered();
        $maxQuantity = $product->supplier?->code === 'vip-reseller' ? 10 : 1;
        $ipaymuChannels = IPaymuGateway::CHECKOUT_CHANNELS;

        return view('checkout', compact('product', 'gateways', 'maxQuantity', 'ipaymuChannels'));
    }

    public function quote(Request $request, PaymentService $payments)
    {
        $data = $request->validate([
            'product_id' => 'required|integer',
            'gateway_code' => 'nullable|string|max:64',
            'quantity' => 'nullable|integer|min:1|max:10',
            'ipaymu_method' => 'nullable|string|max:32',
            'ipaymu_channel' => 'nullable|string|max:32',
        ]);
        $gateway = $data['gateway_code'] ?? 'balance';

        if ($gateway !== 'balance' && ! PaymentGatewayConfig::where('code', $gateway)->where('is_active', true)->exists()) {
            return response()->json(['message' => 'Metode pembayaran tidak tersedia.'], 422);
        }

        $product = Product::available()->findOrFail($data['product_id']);
        $quote = $payments->quote($product, $request->user(), $gateway);
        $quantity = (int) ($data['quantity'] ?? 1);
        $checkoutTotal = $quote['total'] * $quantity;
        $minimumAmount = $gateway === 'ipaymu'
            ? IPaymuGateway::minimumAmountFor($data['ipaymu_method'] ?? 'qris', $data['ipaymu_channel'] ?? 'mpm')
            : 0;
        $available = $minimumAmount === 0 || $checkoutTotal >= $minimumAmount;

        return response()->json(array_merge($quote, [
            'checkout_total' => $checkoutTotal,
            'minimum_amount' => $minimumAmount,
            'available' => $available,
            'message' => $available ? null : 'Minimal channel ini Rp '.number_format($minimumAmount, 0, ',', '.').'. Tambah jumlah pesanan atau gunakan Saldo Member.',
        ]));
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
            'quantity' => 'nullable|integer|min:1|max:10',
            'gateway_code' => 'required|string',
            'ipaymu_method' => 'nullable|string|max:32',
            'ipaymu_channel' => 'nullable|string|max:32',
            'buyer_phone' => 'nullable|string|max:32',
            'buyer_email' => 'nullable|email|max:128',
        ]);

        try {
            $trx = $orders->checkout($data, $request->user());
        } catch (\RuntimeException $e) {
            return back()->withErrors(['checkout' => $e->getMessage()])->withInput();
        } catch (\Throwable $e) {
            report($e);

            return back()->withErrors(['checkout' => 'Checkout gagal diproses. Silakan coba lagi.'])->withInput();
        }

        return redirect()->route('payment.show', $trx->invoice_code);
    }
}
