<?php

namespace App\Support;

use App\Models\Rental;

class VerificationMessage
{
    public static function hasResubmissionRequest(Rental $rental): bool
    {
        $rental->loadMissing(['payments', 'verification']);

        return ($rental->status === 'pending_payment'
                && filled($rental->payments->firstWhere('type', 'dp')?->rejection_reason)
                && $rental->expires_at?->isFuture())
            || ($rental->status === 'pending_verification'
                && filled($rental->verification?->rejection_reason)
                && $rental->resubmission_expires_at?->isFuture());
    }

    public static function waLink(Rental $rental): string
    {
        $rental->loadMissing(['user', 'bike', 'payments', 'verification']);
        $accepted = $rental->status === 'approved';
        $payment = $rental->payments->firstWhere('type', 'dp');

        if (self::hasResubmissionRequest($rental)) {
            $isPayment = $rental->status === 'pending_payment';
            $reason = $isPayment ? $payment?->rejection_reason : $rental->verification?->rejection_reason;
            $deadline = $isPayment ? $rental->expires_at : $rental->resubmission_expires_at;
            $what = $isPayment ? 'bukti pembayaran DP' : 'dokumen KTP dan SIM C';
            $message = "Halo {$rental->user->name}, {$what} untuk {$rental->bike->name} ({$rental->booking_code}) ditolak. Alasan: {$reason}. Silakan "
                .($isPayment ? 'bayar dan unggah ulang bukti pembayaran' : 'unggah ulang dokumen')
                .' sebelum '.$deadline->locale('id')->translatedFormat('d M Y, H:i').' WIB (3 jam). Terima kasih, Mitra Jalan.';
        } elseif ($accepted) {
            $message = "Halo {$rental->user->name}, verifikasi pembayaran dan dokumen untuk {$rental->bike->name} ({$rental->booking_code}) telah diterima. Silakan datang sesuai jadwal sewa. Terima kasih, Mitra Jalan.";
        } else {
            $message = "Halo {$rental->user->name}, verifikasi untuk {$rental->bike->name} ({$rental->booking_code}) belum dapat diterima. ".($rental->cancelled_reason ?? 'Silakan cek riwayat pesanan.').' Mitra Jalan.';
        }

        return 'https://wa.me/'.$rental->user->phone_number.'?text='.rawurlencode($message);
    }
}
