<?php

namespace App\Services;

use App\Exceptions\BikeNotAvailableException;
use App\Exceptions\InvalidBookingPeriodException;
use App\Exceptions\SlotNotAvailableException;
use App\Models\Bike;
use App\Models\Payment;
use App\Models\Rental;
use App\Models\User;
use App\Models\Verification;
use Carbon\Carbon;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class BookingService
{
    public function __construct(
        private readonly PricingService $pricing,
        private readonly AvailabilityService $availability,
    ) {
    }

    /**
     * Membuat rental (status pending_payment), verifikasi (pending), dan payment DP (pending).
     * Semua nilai dihitung ulang di server; input frontend tidak dipercaya.
     *
     * $data: bike_id, start_time, end_time, ktp_photo, sim_photo
     * (ktp_photo dan sim_photo adalah path file yang sudah disimpan di disk privat).
     *
     * @throws InvalidBookingPeriodException
     * @throws BikeNotAvailableException
     * @throws SlotNotAvailableException
     */
    public function create(User $user, array $data): Rental
    {
        $start = Carbon::parse($data['start_time']);
        $end = Carbon::parse($data['end_time']);

        $this->validatePeriod($start, $end);

        return DB::transaction(function () use ($user, $data, $start, $end) {
            // Kunci baris motor sampai transaksi selesai (mencegah double booking)
            $bike = Bike::lockForUpdate()->findOrFail($data['bike_id']);

            if ($bike->status !== 'available') {
                throw new BikeNotAvailableException();
            }

            if ($this->availability->hasConflict($bike->id, $start, $end)) {
                throw new SlotNotAvailableException();
            }

            $hours = $this->pricing->totalHours($start, $end);
            $price = $this->pricing->calculate($bike->hourly_rate, $bike->daily_rate, $hours);

            $rental = $this->createRentalWithUniqueCode([
                'user_id' => $user->id,
                'bike_id' => $bike->id,
                'start_time' => $start,
                'end_time' => $end,
                'total_hours' => $hours,
                // Snapshot tarif: perubahan tarif motor tidak memengaruhi transaksi ini
                'hourly_rate_applied' => $bike->hourly_rate,
                'daily_rate_applied' => $bike->daily_rate,
                'total_price' => $price['total_price'],
                'dp_amount' => $price['dp_amount'],
                'balance_amount' => $price['balance_amount'],
                'payment_status' => 'unpaid',
                'status' => 'pending_payment',
                'expires_at' => now()->addMinutes((int) config('rental.lock_minutes')),
            ]);

            Verification::create([
                'rental_id' => $rental->id,
                'ktp_photo' => $data['ktp_photo'],
                'sim_photo' => $data['sim_photo'],
                'status' => 'pending',
            ]);

            Payment::create([
                'rental_id' => $rental->id,
                'order_id' => $rental->booking_code . '-DP',
                'type' => 'dp',
                'method' => 'midtrans',
                'gross_amount' => $price['dp_amount'],
                'payment_status' => 'pending',
            ]);

            return $rental;
        });
    }

    private function validatePeriod(Carbon $start, Carbon $end): void
    {
        if ($end->lessThanOrEqualTo($start)) {
            throw new InvalidBookingPeriodException('Waktu selesai harus setelah waktu mulai.');
        }

        if ($start->lessThan(now())) {
            throw new InvalidBookingPeriodException('Waktu mulai tidak boleh di masa lalu.');
        }

        $minHours = (int) config('rental.min_hours');
        $seconds = $end->getTimestamp() - $start->getTimestamp();

        if ($seconds < $minHours * 3600) {
            throw new InvalidBookingPeriodException("Durasi sewa minimal {$minHours} jam.");
        }
    }

    /**
     * Format kode: BK-YYYYMMDD-0001. Dua motor berbeda bisa dipesan bersamaan
     * (kunci baris motor tidak saling menghalangi), jadi tabrakan kode dicoba ulang.
     */
    private function createRentalWithUniqueCode(array $attributes): Rental
    {
        $prefix = 'BK-' . now()->format('Ymd') . '-';
        $base = Rental::where('booking_code', 'like', $prefix . '%')->count();

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $code = $prefix . str_pad((string) ($base + $attempt), 4, '0', STR_PAD_LEFT);

            try {
                return Rental::create($attributes + ['booking_code' => $code]);
            } catch (UniqueConstraintViolationException) {
                continue;
            }
        }

        throw new RuntimeException('Gagal membuat kode booking yang unik, silakan coba lagi.');
    }
}
