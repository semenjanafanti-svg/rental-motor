<?php

use App\Models\Rental;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Pesanan yang DP-nya tidak dibayar (bukti belum diunggah) sampai expires_at -> expired.
// Lokal: php artisan schedule:work | Server: cron * * * * * php artisan schedule:run
Schedule::call(function () {
    Rental::where('status', 'pending_payment')
        ->where('expires_at', '<=', now())
        ->pluck('id')
        ->each(function ($id) {
            DB::transaction(function () use ($id) {
                $rental = Rental::lockForUpdate()->find($id);

                // Cek ulang: bisa jadi bukti bayar baru saja masuk
                if (! $rental || $rental->status !== 'pending_payment' || $rental->expires_at->isFuture()) {
                    return;
                }

                $rental->update(['status' => 'expired']);
                $rental->payments()
                    ->where('type', 'dp')
                    ->where('payment_status', 'pending')
                    ->update(['payment_status' => 'expire']);
            });
        });
})->everyMinute()->name('expire-pending-rentals');
