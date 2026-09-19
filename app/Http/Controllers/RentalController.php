<?php

namespace App\Http\Controllers;

use App\Models\Rental;
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

    public function show(Request $request, Rental $rental): View
    {
        // 404 (bukan 403) agar keberadaan pesanan orang lain tidak terungkap
        abort_unless($rental->user_id === $request->user()->id, 404);

        $rental->load(['bike', 'payments', 'verification']);

        return view('rentals.show', ['rental' => $rental]);
    }
}
