<?php

namespace App\Http\Controllers;

use App\Models\Rental;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class VerificationDocumentController extends Controller
{
    public function resubmit(Request $request, Rental $rental): RedirectResponse
    {
        abort_unless($rental->user_id === $request->user()->id, 404);

        $verification = $rental->verification;
        abort_unless($rental->status === 'pending_verification' && $verification?->rejection_count === 1 && $verification->rejection_reason, 404);

        $request->validate([
            'ktp_photo' => ['required', 'file', 'mimes:jpg,jpeg,png', 'max:2048'],
            'sim_photo' => ['required', 'file', 'mimes:jpg,jpeg,png', 'max:2048'],
        ]);

        $disk = Storage::disk('local');
        $ktp = $disk->putFile('verifications/ktp', $request->file('ktp_photo'));
        $sim = $disk->putFile('verifications/sim', $request->file('sim_photo'));
        $old = [$verification->ktp_photo, $verification->sim_photo];

        $verification->update([
            'ktp_photo' => $ktp,
            'sim_photo' => $sim,
            'status' => 'pending',
            'rejection_reason' => null,
        ]);
        $disk->delete(array_filter($old));

        return back()->with('status', 'Dokumen baru terkirim. Admin akan memverifikasinya kembali.');
    }
}
