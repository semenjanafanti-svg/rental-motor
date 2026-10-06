<?php

namespace App\Services;

use App\Models\Rental;
use Illuminate\Support\Facades\DB;

class RentalExpirationService
{
    /**
     * Pembayaran atau unggah ulang dokumen yang melewati batas menjadi expired,
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

        $documentQuery = Rental::where('status', 'pending_verification')
            ->whereNotNull('resubmission_expires_at')
            ->where('resubmission_expires_at', '<=', now())
            ->when($userId, fn ($query) => $query->where('user_id', $userId));

        $documentQuery->select('id')->chunkById(100, function ($rentals) use (&$count) {
            foreach ($rentals as $rental) {
                DB::transaction(function () use ($rental, &$count) {
                    $locked = Rental::lockForUpdate()->find($rental->id);

                    if (! $locked
                        || $locked->status !== 'pending_verification'
                        || $locked->resubmission_expires_at?->isFuture()) {
                        return;
                    }

                    $locked->update(['status' => 'expired']);
                    $locked->payments()
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
