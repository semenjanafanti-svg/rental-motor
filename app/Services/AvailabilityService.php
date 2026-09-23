<?php

namespace App\Services;

use App\Models\Rental;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;

class AvailabilityService
{
    /** Status rental yang mengunci slot motor. */
    public const BLOCKING_STATUSES = [
        'pending_payment',
        'pending_verification',
        'approved',
        'active',
    ];

    /**
     * Overlap = (start_existing < end_requested) AND (end_existing > start_requested)
     * Sewa yang bersambung tepat di jam yang sama tidak dianggap bentrok.
     */
    public function hasConflict(int $bikeId, CarbonInterface $start, CarbonInterface $end): bool
    {
        return $this->blockingQuery($bikeId)
            ->where('start_time', '<', $end)
            ->where('end_time', '>', $start)
            ->exists();
    }

    /**
     * Rentang waktu yang sudah terisi untuk kalender ketersediaan.
     * Hanya mengembalikan waktu, tanpa data penyewa.
     *
     * @return array<int, array{start:string, end:string}>
     */
    public function bookedRanges(int $bikeId, CarbonInterface $from, CarbonInterface $to): array
    {
        return $this->blockingQuery($bikeId)
            ->where('start_time', '<', $to)
            ->where('end_time', '>', $from)
            ->orderBy('start_time')
            ->get(['start_time', 'end_time'])
            ->map(fn (Rental $rental) => [
                'start' => $rental->start_time->format('Y-m-d H:i'),
                'end' => $rental->end_time->format('Y-m-d H:i'),
            ])
            ->all();
    }

    /**
     * Tanggal mulai yang bisa dipilih untuk jam mulai tertentu, beserta jumlah hari
     * maksimal yang masih kosong sejak tanggal itu.
     *
     * Contoh: ['2026-09-19' => 3, '2026-09-20' => 2]
     * (mulai 19 Sep jam yang dipilih, motor kosong sampai 3 hari ke depan).
     *
     * Sewa selalu kelipatan 24 jam, jadi hari kosong = selisih ke sewa berikutnya
     * dibagi 24 jam, dibulatkan ke bawah. Ini hanya pemandu tampilan; validasi
     * akhir tetap di BookingService (hasConflict di dalam transaksi).
     *
     * @return array<string, int>
     */
    public function availableStartDates(int $bikeId, string $time, CarbonInterface $from, int $windowDays = 90): array
    {
        [$hour, $minute] = array_map('intval', explode(':', $time));

        $minDays = max(1, (int) config('rental.min_days'));
        $maxDays = (int) config('rental.max_days');

        $firstDay = $from->copy()->startOfDay();
        $windowEnd = $firstDay->copy()->addDays($windowDays + $maxDays + 1);

        $rentals = $this->blockingQuery($bikeId)
            ->where('end_time', '>', $firstDay)
            ->where('start_time', '<', $windowEnd)
            ->get(['start_time', 'end_time']);

        $dates = [];

        for ($i = 0; $i < $windowDays; $i++) {
            $start = $firstDay->copy()->addDays($i)->setTime($hour, $minute);

            if ($start->lessThan(now())) {
                continue; // jam mulai sudah lewat
            }

            $freeDays = $maxDays;
            $blocked = false;

            foreach ($rentals as $rental) {
                if ($rental->end_time <= $start) {
                    continue; // sewa itu sudah selesai sebelum jam mulai
                }

                if ($rental->start_time < $start) {
                    $blocked = true; // jam mulai jatuh di tengah sewa lain
                    break;
                }

                $daysUntilNext = intdiv($rental->start_time->getTimestamp() - $start->getTimestamp(), 86400);
                $freeDays = min($freeDays, $daysUntilNext);
            }

            if (! $blocked && $freeDays >= $minDays) {
                $dates[$start->format('Y-m-d')] = $freeDays;
            }
        }

        return $dates;
    }

    /** ID motor yang terkunci pada rentang waktu tertentu (untuk filter katalog). */
    public function busyBikeIds(CarbonInterface $from, CarbonInterface $to): array
    {
        return $this->busyBikeQuery($from, $to)
            ->pluck('bike_id')
            ->unique()
            ->all();
    }

    /**
     * Subquery motor yang terkunci pada suatu periode.
     *
     * Dipakai oleh katalog agar database yang menyaring motor, tanpa memindahkan
     * seluruh ID rental aktif ke PHP lalu membentuk WHERE NOT IN yang besar.
     */
    public function busyBikeQuery(CarbonInterface $from, CarbonInterface $to): Builder
    {
        return Rental::query()
            ->whereIn('status', self::BLOCKING_STATUSES)
            ->where(fn ($q) => $q->where('status', '!=', 'pending_payment')->orWhere('expires_at', '>', now()))
            ->where('start_time', '<', $to)
            ->where('end_time', '>', $from);
    }

    private function blockingQuery(int $bikeId): Builder
    {
        return Rental::query()
            ->where('bike_id', $bikeId)
            ->whereIn('status', self::BLOCKING_STATUSES)
            ->where(function ($q) {
                // pending_payment yang sudah kedaluwarsa tidak menghalangi
                $q->where('status', '!=', 'pending_payment')
                    ->orWhere('expires_at', '>', now());
            });
    }
}
