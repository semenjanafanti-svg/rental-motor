<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\Verification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Menyajikan berkas di disk privat (KTP, SIM, bukti bayar) hanya untuk yang berhak.
 * File tidak pernah bisa diakses lewat URL publik /storage.
 */
class PrivateFileController extends Controller
{
    /** KTP / SIM: hanya staf. */
    public function verification(Request $request, Verification $verification, string $type): StreamedResponse
    {
        abort_unless($request->user()->isStaff(), 403);

        $path = $type === 'ktp' ? $verification->ktp_photo : $verification->sim_photo;

        return $this->serve($path);
    }

    /** Bukti bayar: staf atau pemilik pesanan. */
    public function payment(Request $request, Payment $payment): StreamedResponse
    {
        $payment->loadMissing('rental');

        abort_unless(
            $request->user()->isStaff() || $payment->rental->user_id === $request->user()->id,
            403
        );

        return $this->serve($payment->proof_photo);
    }

    private function serve(?string $path): StreamedResponse
    {
        $disk = Storage::disk('local');

        abort_unless($path && $disk->exists($path), 404);

        return $disk->response($path, null, ['Cache-Control' => 'private, no-store']);
    }
}
