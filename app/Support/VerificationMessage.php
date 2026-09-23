<?php

namespace App\Support;

use App\Models\Rental;

class VerificationMessage
{
    public static function waLink(Rental $rental): string
    {
        $rental->loadMissing(['user', 'bike']);
        $accepted = $rental->status === 'approved';
        $message = $accepted
            ? "Halo {$rental->user->name}, verifikasi pembayaran dan dokumen untuk {$rental->bike->name} ({$rental->booking_code}) telah diterima. Silakan datang sesuai jadwal sewa. Terima kasih, Mitra Jalan."
            : "Halo {$rental->user->name}, verifikasi untuk {$rental->bike->name} ({$rental->booking_code}) belum dapat diterima. " . ($rental->cancelled_reason ?? 'Silakan cek riwayat pesanan.') . ' Mitra Jalan.';

        return 'https://wa.me/' . $rental->user->phone_number . '?text=' . rawurlencode($message);
    }
}
