<?php

namespace App\Http\Controllers;

use App\Exceptions\PaymentException;
use App\Models\Rental;
use App\Services\PaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    /** Penyewa mengunggah bukti pembayaran DP (QRIS). */
    public function storeProof(Request $request, Rental $rental, PaymentService $payments): RedirectResponse
    {
        abort_unless($rental->user_id === $request->user()->id, 404);

        $request->validate([
            'proof_photo' => ['required', 'file', 'mimes:jpg,jpeg,png', 'mimetypes:image/jpeg,image/png', 'max:4096'],
        ], [
            'proof_photo.required' => 'Bukti pembayaran wajib diunggah.',
            'proof_photo.mimes' => 'Bukti pembayaran harus berformat JPG atau PNG.',
            'proof_photo.mimetypes' => 'Bukti pembayaran harus berformat JPG atau PNG.',
            'proof_photo.max' => 'Ukuran bukti pembayaran maksimal 4 MB.',
        ]);

        try {
            $payments->submitDpProof($rental, $request->file('proof_photo'));
        } catch (PaymentException $e) {
            return back()->withErrors(['booking' => $e->getMessage()]);
        }

        return redirect()
            ->route('rentals.show', $rental)
            ->with('status', 'Bukti pembayaran terkirim. Admin akan memverifikasinya.');
    }
    
    /** Penyewa membatalkan pesanan (H-3 atau lebih awal). */
    public function cancel(Request $request, Rental $rental, PaymentService $payments): RedirectResponse
    {
        abort_unless($rental->user_id === $request->user()->id, 404);

        try {
            $payments->cancelByCustomer($rental);
        } catch (PaymentException $e) {
            return back()->withErrors(['booking' => $e->getMessage()]);
        }

        return redirect()->route('rentals.show', $rental)->with('status', 'Pesanan dibatalkan.');
    }
}
