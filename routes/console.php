<?php

use App\Models\Rental;
use App\Models\RentalReminder;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;
use App\Services\RentalExpirationService;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Pesanan yang DP-nya tidak dibayar sampai expires_at -> expired.
// Logikanya ada di RentalExpirationService (juga dipanggil saat halaman pesanan dibuka).
// Lokal: php artisan schedule:work | Server: cron * * * * * php artisan schedule:run
Schedule::call(fn() => app(RentalExpirationService::class)->expireOverdue())
    ->everyMinute()->name('expire-pending-rentals');

// Rental approved yang tidak diambil melewati toleransi no-show -> no_show, DP hangus, slot lepas.
Schedule::call(function () {
    $toleranceMinutes = (int) config('rental.no_show_tolerance_minutes');

    Rental::where('status', 'approved')
        ->where('start_time', '<=', now()->subMinutes($toleranceMinutes))
        ->select('id')
        ->chunkById(100, function ($rentals) use ($toleranceMinutes) {
            foreach ($rentals as $rental) {
                $id = $rental->id;
                DB::transaction(function () use ($id, $toleranceMinutes) {
                    $rental = Rental::lockForUpdate()->find($id);

                    if (! $rental || $rental->status !== 'approved') {
                        return;
                    }

                    // Cek ulang di dalam transaksi: bisa jadi baru saja di-check-in
                    if ($rental->start_time->copy()->addMinutes($toleranceMinutes)->isFuture()) {
                        return;
                    }

                    $rental->update([
                        'status' => 'no_show',
                        'cancelled_reason' => 'Penyewa tidak datang mengambil motor sampai batas toleransi.',
                    ]);
                });
            }
        });
})->everyFiveMinutes()->name('mark-no-show-rentals');

// Rental active yang melewati end_time dan belum punya reminder 'overdue' -> buatkan satu,
// supaya muncul di halaman "Reminder Hari Ini" tanpa duplikat.
Schedule::call(function () {
    Rental::where('status', 'active')
        ->where('end_time', '<=', now())
        ->whereDoesntHave('reminders', fn($q) => $q->where('type', 'overdue'))
        ->select('id')
        ->chunkById(100, function ($rentals) {
            foreach ($rentals as $rental) {
                $id = $rental->id;
                DB::transaction(function () use ($id) {
                    $rental = Rental::lockForUpdate()->find($id);

                    if (! $rental || $rental->status !== 'active') {
                        return;
                    }

                    if ($rental->reminders()->where('type', 'overdue')->exists()) {
                        return;
                    }

                    RentalReminder::create([
                        'rental_id' => $rental->id,
                        'type' => 'overdue',
                        'scheduled_at' => now(),
                        'status' => 'pending',
                    ]);
                });
            }
        });
})->everyFiveMinutes()->name('create-overdue-reminders');
