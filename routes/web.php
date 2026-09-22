<?php

use App\Http\Controllers\BikeController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\PrivateFileController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RentalController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/motor');

// Publik
Route::get('/motor', [BikeController::class, 'index'])->name('bikes.index');
Route::get('/motor/{bike}', [BikeController::class, 'show'])->name('bikes.show');
Route::get('/motor/{bike}/availability', [BikeController::class, 'availability'])->name('bikes.availability');
Route::get('/motor/{bike}/available-dates', [BikeController::class, 'dates'])->name('bikes.dates');
Route::get('/motor/{bike}/quote', [BikeController::class, 'quote'])->name('bikes.quote');

// Khusus customer
Route::middleware(['auth', 'role:customer'])->group(function () {
    Route::get('/motor/{bike}/checkout', [BookingController::class, 'create'])->name('bookings.create');
    Route::post('/motor/{bike}/checkout', [BookingController::class, 'store'])->name('bookings.store');
    Route::get('/riwayat', [RentalController::class, 'index'])->name('rentals.index');
    Route::get('/riwayat/{rental}', [RentalController::class, 'show'])->name('rentals.show');
    Route::post('/riwayat/{rental}/bukti-bayar', [PaymentController::class, 'storeProof'])->name('rentals.proof');
    Route::post('/riwayat/{rental}/batal', [PaymentController::class, 'cancel'])->name('rentals.cancel');
});

// Berkas privat (KTP, SIM, bukti bayar): otorisasi dicek di controller
Route::middleware('auth')->group(function () {
    Route::get('/berkas/verifikasi/{verification}/{type}', [PrivateFileController::class, 'verification'])
        ->whereIn('type', ['ktp', 'sim'])
        ->name('files.verification');
    Route::get('/berkas/pembayaran/{payment}', [PrivateFileController::class, 'payment'])
        ->name('files.payment');
});

// Tujuan setelah login (Breeze mengarah ke route bernama "dashboard")
Route::get('/dashboard', function () {
    return auth()->user()->isStaff()
        ? redirect('/admin') // panel Filament
        : redirect()->route('bikes.index');
})->middleware('auth')->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
