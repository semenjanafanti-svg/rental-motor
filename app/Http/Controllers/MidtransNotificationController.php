<?php

namespace App\Http\Controllers;

use App\Services\MidtransService;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Webhook Midtrans. Dikecualikan dari CSRF (bootstrap/app.php) dan dilindungi signature_key.
 */
class MidtransNotificationController extends Controller
{
    public function __invoke(Request $request, MidtransService $midtrans, PaymentService $payments): JsonResponse
    {
        $payload = $request->json()->all();

        if (! $midtrans->isValidSignature($payload)) {
            Log::warning('Midtrans: signature notifikasi tidak valid', [
                'order_id' => $payload['order_id'] ?? null,
            ]);

            return response()->json(['message' => 'Invalid signature'], 403);
        }

        Log::info('Midtrans: notifikasi diterima', [
            'order_id' => $payload['order_id'],
            'transaction_status' => $payload['transaction_status'] ?? null,
        ]);

        $payments->applyNotification($payload);

        return response()->json(['message' => 'OK']);
    }
}
