<?php

namespace App\Services;

use App\Models\Rental;
use Illuminate\Support\Facades\DB;

class RentalExpirationService
{
    /**
     * Tandai pesanan approved yang belum di-check-in setelah batas toleransi sebagai no-show.
     * Dipakai scheduler dan saat halaman pesanan/admin dibuka agar status tidak bergantung
     * sepenuhnya pada cron yang berjalan tepat waktu.
     */
    public function expireNoShows(?int $userId = null): int
    {
        $toleranceMinutes = (int) config('rental.no_show_tolerance_minutes');
        $deadline = now()->subMinutes($toleranceMinutes);
        $query = Rental::query()
            ->where('status', 'approved')
            ->where('start_time', '<=', $deadline)
            ->when($userId, fn ($query) => $query->where('user_id', $userId));
        $count = 0;

        $query->select('id')->chunkById(100, function ($rentals) use (&$count, $toleranceMinutes): void {
            foreach ($rentals as $rental) {
                DB::transaction(function () use ($rental, &$count, $toleranceMinutes): void {
                    $locked = Rental::query()->lockForUpdate()->find($rental->id);

                    if (! $locked
                        || $locked->status !== 'approved'
                        || $locked->start_time->copy()->addMinutes($toleranceMinutes)->isFuture()) {
                        return;
                    }

                    $locked->update([
                        'status' => 'no_show',
                        'cancelled_reason' => 'Penyewa tidak datang mengambil motor sampai batas toleransi.',
                    ]);

                    $count++;
                });
            }
        });

        return $count;
    }

    /**
     * Pembayaran yang melewati batas menjadi expired dan payment DP yang masih pending menjadi expire.
     * $userId dipakai untuk membatasi ke pesanan satu penyewa (saat halamannya dibuka).
     */
    public function expireOverdue(?int $userId = null): int
    {
        $query = Rental::where('status', 'pending_payment')
            ->where('expires_at', '<=', now())
            ->when($userId, fn ($query) => $query->where('user_id', $userId));

        $count = 0;

        $query->select('id')->chunkById(100, function ($rentals) use (&$count) {
            foreach ($rentals as $rental) {
                DB::transaction(function () use ($rental, &$count) {
                    $id = $rental->id;
                    $rental = Rental::lockForUpdate()->find($id);

                    // Cek ulang: bukti bayar bisa saja baru masuk
                    if (! $rental || $rental->status !== 'pending_payment' || $rental->expires_at->isFuture()) {
                        return;
                    }

                    $rental->update(['status' => 'expired']);
                    $rental->payments()
                        ->where('type', 'dp')
                        ->where('payment_status', 'pending')
                        ->update(['payment_status' => 'expire']);

                    $count++;
                });
            }
        });

        return $count;
    }
}
