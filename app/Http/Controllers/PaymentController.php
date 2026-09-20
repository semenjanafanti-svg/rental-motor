<?php

namespace App\Http\Controllers;

use App\Exceptions\MidtransException;
use App\Exceptions\PaymentException;
use App\Models\Rental;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    /** Snap token untuk membuka popup pembayaran DP. */
    public function snapToken(Request $request, Rental $rental, PaymentService $payments): JsonResponse
    {
        abort_unless($rental->user_id === $request->user()->id, 404);

        try {
            $token = $payments->startDpPayment($rental);
        } catch (PaymentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (MidtransException $e) {
            report($e);

            return response()->json(['message' => 'Layanan pembayaran sedang bermasalah. Coba lagi beberapa saat.'], 502);
        }

        return response()->json(['token' => $token]);
    }
}
