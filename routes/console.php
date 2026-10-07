<?php

use App\Services\OverdueReminderService;
use App\Services\RentalExpirationService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Pesanan yang DP-nya tidak dibayar sampai expires_at -> expired.
// Logikanya ada di RentalExpirationService (juga dipanggil saat halaman pesanan dibuka).
// Lokal: php artisan schedule:work | Server: cron * * * * * php artisan schedule:run
Schedule::call(fn () => app(RentalExpirationService::class)->expireOverdue())
    ->everyMinute()->name('expire-pending-rentals');

// Rental approved yang melewati toleransi no-show menjadi no_show dan slot dilepas.
Schedule::call(fn () => app(RentalExpirationService::class)->expireNoShows())
    ->everyMinute()->name('mark-no-show-rentals');

// Rental active yang melewati end_time dan belum punya reminder 'overdue' -> buatkan satu,
// supaya muncul di halaman "Reminder Hari Ini" tanpa duplikat.
Schedule::call(fn () => app(OverdueReminderService::class)->createMissing())
    ->everyFiveMinutes()->name('create-overdue-reminders');
