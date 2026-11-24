<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\Rental;
use App\Services\RentalExpirationService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Dashboard analisis untuk customer: ringkasan dan grafik dari riwayat sewanya sendiri.
 * Data diambil langsung dari database operasional (real time, hanya milik user ini),
 * lalu diagregasi di browser supaya filter tiap grafik bisa diganti tanpa memuat ulang halaman.
 */
class CustomerDashboardController extends Controller
{
    /** Status operasional -> kelompok yang ditampilkan ke customer. */
    private const STATUS_GROUPS = [
        'completed' => 'Selesai',
        'active' => 'Berjalan',
        'approved' => 'Berjalan',
        'pending_payment' => 'Menunggu',
        'pending_verification' => 'Menunggu',
        'cancelled' => 'Dibatalkan',
        'expired' => 'Kedaluwarsa',
        'no_show' => 'Tidak datang',
    ];

    public function __invoke(Request $request, RentalExpirationService $expiration): View
    {
        $user = $request->user();
        $expiration->expireOverdue($user->id);
        $expiration->expireNoShows($user->id);

        $rentals = $user->rentals()
            ->with(['bike', 'rentalReturn', 'payments'])
            ->orderBy('start_time')
            ->get();

        $rentalRows = $rentals->map(fn (Rental $rental) => [
            'tahun' => $rental->start_time->year,
            'bulan' => $rental->start_time->month,
            'hari_ke' => $rental->start_time->dayOfWeekIso, // 1 = Senin
            'status' => self::STATUS_GROUPS[$rental->status] ?? 'Lainnya',
            'motor' => $rental->bike?->name ?? 'Motor dihapus',
            'hari' => (int) ceil($rental->start_time->diffInHours($rental->end_time, true) / 24),
            // null = belum dikembalikan
            'telat' => $rental->rentalReturn ? $rental->rentalReturn->late_hours > 0 : null,
        ])->values();

        // Uang yang benar-benar keluar: pembayaran lunas dikurangi refund.
        $paymentRows = $rentals->flatMap->payments
            ->filter(fn (Payment $p) => $p->paid_at && in_array($p->payment_status, ['settlement', 'refund'], true))
            ->map(fn (Payment $p) => [
                'tahun' => $p->paid_at->year,
                'bulan' => $p->paid_at->month,
                'jenis' => $p->type === 'fine' ? 'Denda' : 'Sewa',
                'nominal' => (float) $p->gross_amount - (float) $p->refunded_amount,
            ])
            ->filter(fn (array $p) => $p['nominal'] > 0)
            ->values();

        // Pilihan tahun mengikuti tahun mulai sewa (DP yang dibayar akhir tahun sebelumnya
        // tetap terlihat di "Semua tahun").
        $years = $rentalRows->pluck('tahun')->unique()->sort()->values();

        return view('customer.dashboard', [
            'hasData' => $rentals->isNotEmpty(),
            'years' => $years,
            'payload' => [
                'rentals' => $rentalRows,
                'payments' => $paymentRows,
            ],
        ]);
    }
}
