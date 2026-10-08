<?php

namespace App\Services;

use App\Models\Rental;
use App\Models\User;

class WhatsAppTemplateService
{
    public function passwordResetLink(User $user, string $temporaryPassword): string
    {
        $message = "Halo {$user->name}, owner Mitra Jalan telah mengatur ulang password akun Anda. "
            ."Password sementara: {$temporaryPassword}. Silakan masuk lalu ganti password Anda dari halaman profil.";

        return 'https://wa.me/'.$user->phone_number.'?text='.rawurlencode($message);
    }

    public function rejectedDocumentLink(Rental $rental): string
    {
        return $this->link($rental, 'documents');
    }

    public function rejectedProofLink(Rental $rental): string
    {
        return $this->link($rental, 'proof');
    }

    private function link(Rental $rental, string $rejectionType): string
    {
        $rental->loadMissing(['user', 'bike', 'payments']);

        $reason = $rejectionType === 'documents'
            ? $rental->verification_rejection_reason
            : $rental->payments->firstWhere('type', 'dp')?->rejection_reason;
        $rejection = $rejectionType === 'documents'
            ? 'dokumen (KTP/SIM) ditolak'
            : 'bukti pembayaran DP ditolak';
        $message = "Halo {$rental->user->name}, pesanan {$rental->booking_code} untuk {$rental->bike->name} "
            ."DIBATALKAN karena {$rejection}. Alasan: {$reason}. DP Anda sudah dicatat untuk direfund. "
            .'Silakan booking ulang saat Anda siap. Terima kasih, Mitra Jalan.';

        return 'https://wa.me/'.$rental->user->phone_number.'?text='.rawurlencode($message);
    }
}
