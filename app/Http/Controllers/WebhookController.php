<?php

namespace App\Http\Controllers;

use App\Services\PaymentService;
use App\Services\ProviderFactory;
use Illuminate\Http\Request;

class WebhookController extends Controller
{
    public function gateway(Request $request, string $gateway, PaymentService $payments)
    {
        // Gateway nonaktif tetap harus menerima callback invoice lama. Status
        // nonaktif hanya mencegah checkout baru memakai gateway tersebut.
        $provider = ProviderFactory::gatewayByCode($gateway, activeOnly: false);

        if (! $provider) {
            return response()->json(['ok' => false, 'message' => 'Unknown gateway'], 404);
        }

        $result = $provider->handleCallback($request->all(), $request->headers->all());

        if (! ($result['ok'] ?? false)) {
            return response()->json(['ok' => false, 'reason' => $result['status'] ?? 'invalid'], 400);
        }

        if (($result['status'] ?? '') === 'paid' && ! empty($result['reference_id'])) {
            try {
                $trx = $payments->markPaid(
                    $result['reference_id'],
                    $gateway,
                    $result['raw'] ?? [],
                    $result['amount'] ?? null,
                );
            } catch (\UnexpectedValueException $e) {
                report($e);

                return response()->json(['ok' => false, 'reason' => 'payment_mismatch'], 422);
            }

            if (! $trx) {
                return response()->json(['ok' => false, 'reason' => 'transaction_not_found'], 404);
            }
        }

        return response()->json(['ok' => true]);
    }
}
