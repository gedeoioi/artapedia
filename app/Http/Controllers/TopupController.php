<?php

namespace App\Http\Controllers;

use App\Models\PaymentGatewayConfig;
use App\Payments\IPaymuGateway;
use App\Services\PaymentService;
use Illuminate\Http\Request;

class TopupController extends Controller
{
    public function create()
    {
        $gateways = PaymentGatewayConfig::activeOrdered();
        $ipaymuGateway = $gateways->firstWhere('code', 'ipaymu');
        $ipaymuChannels = $ipaymuGateway?->enabledCheckoutChannels() ?? [];

        if ($ipaymuGateway && $ipaymuChannels === []) {
            $gateways = $gateways->reject(fn (PaymentGatewayConfig $gateway): bool => $gateway->code === 'ipaymu');
        }

        return view('member.topup', compact('gateways', 'ipaymuChannels'));
    }

    public function quote(Request $request)
    {
        $data = $request->validate([
            'amount' => 'required|integer|min:10000|max:10000000',
            'gateway_code' => 'required|string|max:64',
            'ipaymu_method' => 'required_if:gateway_code,ipaymu|nullable|string|max:32',
            'ipaymu_channel' => 'required_if:gateway_code,ipaymu|nullable|string|max:32',
        ]);
        $gateway = PaymentGatewayConfig::where('code', $data['gateway_code'])
            ->where('is_active', true)
            ->firstOrFail();

        if ($gateway->code === 'ipaymu' && ! $gateway->isCheckoutChannelEnabled($data['ipaymu_method'] ?? null, $data['ipaymu_channel'] ?? null)) {
            return response()->json(['message' => 'Channel pembayaran iPaymu tidak tersedia.'], 422);
        }

        $amount = (int) $data['amount'];
        $fee = $gateway->feeFor($amount, $data['ipaymu_method'] ?? null, $data['ipaymu_channel'] ?? null);
        $minimum = $gateway->code === 'ipaymu'
            ? IPaymuGateway::minimumAmountFor($data['ipaymu_method'], $data['ipaymu_channel'])
            : 10_000;
        $available = ($amount + $fee) >= $minimum;

        return response()->json([
            'amount' => $amount,
            'gateway_fee' => $fee,
            'total' => $amount + $fee,
            'minimum_amount' => $minimum,
            'available' => $available,
            'message' => $available ? null : 'Minimal channel ini Rp '.number_format($minimum, 0, ',', '.').'.',
        ]);
    }

    public function store(Request $request, PaymentService $payments)
    {
        $data = $request->validate([
            'amount' => 'required|integer|min:10000|max:10000000',
            'gateway_code' => 'required|string|exists:payment_gateway_configs,code',
            'ipaymu_method' => 'required_if:gateway_code,ipaymu|nullable|string|max:32',
            'ipaymu_channel' => 'required_if:gateway_code,ipaymu|nullable|string|max:32',
        ]);

        try {
            $trx = $payments->topupBalance(
                $request->user(),
                $data['amount'],
                $data['gateway_code'],
                paymentMethod: $data['ipaymu_method'] ?? null,
                paymentChannel: $data['ipaymu_channel'] ?? null,
            );
        } catch (\RuntimeException $e) {
            report($e);

            return back()->withErrors(['topup' => $e->getMessage()])->withInput();
        } catch (\Throwable $e) {
            report($e);

            return back()->withErrors(['topup' => 'Topup gagal dibuat. Silakan coba metode lain.'])->withInput();
        }

        return redirect()->route('payment.show', $trx->invoice_code);
    }
}
