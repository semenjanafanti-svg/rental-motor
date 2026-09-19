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
use Exception;
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
     * Simulasi tanpa menyimpan apa pun: susun periode sewa, cek status motor dan bentrok
     * jadwal, lalu hitung harga. Melempar BookingException jika tidak bisa dipesan.
     *
     * Penyewa hanya memberi tanggal mulai, jam mulai, dan tanggal pengembalian.
     * Jam pengembalian otomatis = jam mulai (durasi selalu kelipatan 24 jam).
     *
     * @return array{total_hours:int, total_price:int, dp_amount:int, balance_amount:int, days:int, start_at:string, end_at:string}
     */
    public function quote(Bike $bike, string $startDate, string $startTime, string $endDate): array
    {
        $period = $this->period($startDate, $startTime, $endDate);
        $price = $this->priceIfBookable($bike, $period['start'], $period['end']);

        return $price + [
            'days' => $period['days'],
            'start_at' => $period['start']->format('Y-m-d H:i'),
            'end_at' => $period['end']->format('Y-m-d H:i'),
        ];
    }

    /**
     * Membuat rental (status pending_payment), verifikasi (pending), dan payment DP (pending).
     * Semua nilai dihitung ulang di server; input frontend tidak dipercaya.
     *
     * $data: bike_id, start_date (Y-m-d), start_time (H:i), end_date (Y-m-d), ktp_photo, sim_photo
     * (ktp_photo dan sim_photo adalah path file yang sudah disimpan di disk privat).
     *
     * @throws InvalidBookingPeriodException
     * @throws BikeNotAvailableException
     * @throws SlotNotAvailableException
     */
    public function create(User $user, array $data): Rental
    {
        $period = $this->period($data['start_date'], $data['start_time'], $data['end_date']);
        $start = $period['start'];
        $end = $period['end'];

        return DB::transaction(function () use ($user, $data, $start, $end) {
            // Kunci baris motor sampai transaksi selesai (mencegah double booking)
            $bike = Bike::lockForUpdate()->findOrFail($data['bike_id']);

            $price = $this->priceIfBookable($bike, $start, $end);

            $rental = $this->createRentalWithUniqueCode([
                'user_id' => $user->id,
                'bike_id' => $bike->id,
                'start_time' => $start,
                'end_time' => $end,
                'total_hours' => $price['total_hours'],
                // Snapshot tarif: perubahan tarif motor tidak memengaruhi transaksi ini.
                // Tarif per jam dipakai untuk menghitung denda keterlambatan.
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

    private function priceIfBookable(Bike $bike, Carbon $start, Carbon $end): array
    {
        if ($bike->status !== 'available') {
            throw new BikeNotAvailableException();
        }

        if ($this->availability->hasConflict($bike->id, $start, $end)) {
            throw new SlotNotAvailableException();
        }

        return $this->pricing->calculate(
            $bike->hourly_rate,
            $bike->daily_rate,
            $this->pricing->totalHours($start, $end)
        );
    }

    /**
     * Susun periode sewa dari tanggal mulai, jam mulai, dan tanggal pengembalian.
     * end = start + (jumlah hari x 24 jam), sehingga jam pengembalian = jam mulai.
     *
     * @return array{start:Carbon, end:Carbon, days:int}
     */
    private function period(string $startDate, string $startTime, string $endDate): array
    {
        $start = $this->parse("{$startDate} {$startTime}", '!Y-m-d H:i', 'Y-m-d H:i', 'Tanggal atau jam mulai tidak valid.');
        $endDay = $this->parse($endDate, '!Y-m-d', 'Y-m-d', 'Tanggal pengembalian tidak valid.');

        $days = (int) round(($endDay->getTimestamp() - $start->copy()->startOfDay()->getTimestamp()) / 86400);

        $minDays = max(1, (int) config('rental.min_days'));
        $maxDays = (int) config('rental.max_days');

        if ($days < $minDays) {
            throw new InvalidBookingPeriodException(
                "Sewa minimal {$minDays} hari (" . ($minDays * 24) . ' jam). Pilih tanggal pengembalian yang lebih akhir.'
            );
        }

        if ($days > $maxDays) {
            throw new InvalidBookingPeriodException("Durasi sewa maksimal {$maxDays} hari.");
        }

        if ($start->lessThan(now())) {
            throw new InvalidBookingPeriodException('Waktu mulai tidak boleh di masa lalu.');
        }

        return [
            'start' => $start,
            'end' => $start->copy()->addDays($days),
            'days' => $days,
        ];
    }

    private function parse(string $value, string $parseFormat, string $checkFormat, string $errorMessage): Carbon
    {
        try {
            $date = Carbon::createFromFormat($parseFormat, $value);
        } catch (Exception) {
            $date = false;
        }

        // Cek balik: menolak nilai seperti 2026-13-45 yang diam-diam "bergeser" oleh PHP
        if (! $date || $date->format($checkFormat) !== $value) {
            throw new InvalidBookingPeriodException($errorMessage);
        }

        return $date;
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
