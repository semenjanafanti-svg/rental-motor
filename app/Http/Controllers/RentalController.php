<?php

namespace App\Http\Controllers;

use App\Models\Rental;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RentalController extends Controller
{
    public function index(Request $request): View
    {
        $rentals = $request->user()
            ->rentals()
            ->with('bike')
            ->latest()
            ->paginate(10);

        return view('rentals.index', ['rentals' => $rentals]);
    }

    public function show(Request $request, Rental $rental, PaymentService $payments): View
    {
        // 404 (bukan 403) agar keberadaan pesanan orang lain tidak terungkap
        abort_unless($rental->user_id === $request->user()->id, 404);

        // Ambil status terbaru dari Midtrans (berguna bila webhook belum/tidak bisa masuk)
        if ($rental->status === 'pending_payment') {
            $payments->syncStatus($rental);
            $rental->refresh();
        }

        $rental->load(['bike', 'payments', 'verification']);

        return view('rentals.show', [
            'rental' => $rental,
            'payable' => $payments->isPayable($rental),
            'clientKey' => config('midtrans.client_key'),
            'snapJsUrl' => config('midtrans.snap_js_url'),
        ]);
    }

    /** JSON status ringan untuk polling halaman pesanan saat menunggu pembayaran. */
    public function status(Request $request, Rental $rental, PaymentService $payments): JsonResponse
    {
        abort_unless($rental->user_id === $request->user()->id, 404);

        if ($rental->status === 'pending_payment') {
            $payments->syncStatus($rental);
            $rental->refresh();
        }

        return response()->json([
            'status' => $rental->status,
            'payment_status' => $rental->payment_status,
        ]);
    }
}
