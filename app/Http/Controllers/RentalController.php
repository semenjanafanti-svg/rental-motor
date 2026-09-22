<?php

namespace App\Http\Controllers;

use App\Models\Rental;
use App\Services\PaymentService;
use Illuminate\Http\Request;
use Illuminate\View\View;
use App\Services\RentalExpirationService;

class RentalController extends Controller
{
    public function index(Request $request, RentalExpirationService $expiration): View
    {
        $expiration->expireOverdue($request->user()->id);

        $rentals = $request->user()
            ->rentals()
            ->with('bike')
            ->latest()
            ->paginate(10);

        return view('rentals.index', ['rentals' => $rentals]);
    }

    public function show(Request $request, Rental $rental, PaymentService $payments, RentalExpirationService $expiration): View
    {
        // 404 (bukan 403) agar keberadaan pesanan orang lain tidak terungkap
        abort_unless($rental->user_id === $request->user()->id, 404);

        $expiration->expireOverdue($request->user()->id);
        $rental->refresh();

        $rental->load(['bike', 'payments', 'verification']);

        return view('rentals.show', [
            'rental' => $rental,
            'payable' => $payments->isPayable($rental),
            'cancellable' => $payments->canCancel($rental),
            'qrisImage' => asset(config('rental.qris.image')),
        ]);
    }
}
