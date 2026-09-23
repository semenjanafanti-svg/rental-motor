<?php

namespace App\Services;

use App\Models\Rental;
use Illuminate\Support\Facades\DB;

class RentalExpirationService
{
    /**
     * pending_payment yang lewat expires_at menjadi expired,
     * dan payment DP yang masih pending menjadi expire.
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
