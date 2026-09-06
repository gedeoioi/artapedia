<?php

namespace App\Http\Controllers;

use App\Services\PaymentService;
use App\Services\ProviderFactory;
use Illuminate\Http\Request;

class WebhookController extends Controller
{
    public function gateway(Request $request, string $gateway, PaymentService $payments)
    {
        $provider = ProviderFactory::gatewayByCode($gateway);

        if (! $provider) {
            return response()->json(['ok' => false, 'message' => 'Unknown gateway'], 404);
        }

        $result = $provider->handleCallback($request->all(), $request->headers->all());

        if (! ($result['ok'] ?? false)) {
            return response()->json(['ok' => false, 'reason' => $result['status'] ?? 'invalid'], 400);
        }

        if (($result['status'] ?? '') === 'paid' && ! empty($result['reference_id'])) {
            $payments->markPaid($result['reference_id'], $gateway, $result['raw'] ?? []);
        }

        return response()->json(['ok' => true]);
    }
}
