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
        if ($this->hasCompletedReturnOnDate($bikeId, $start)) {
            return true;
        }

        return $this->blockingQuery($bikeId)
            ->where(fn (Builder $query) => $query
                ->where('status', 'active')
                ->orWhere(fn (Builder $query) => $query
                    ->where('start_time', '<', $end)
                    ->where('end_time', '>', $start)))
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
        $blockingRanges = $this->blockingQuery($bikeId)
            ->where(fn (Builder $query) => $query
                ->where('status', 'active')
                ->orWhere(fn (Builder $query) => $query
                    ->where('start_time', '<', $to)
                    ->where('end_time', '>', $from)))
            ->orderBy('start_time')
            ->get(['start_time', 'end_time', 'status'])
            ->map(fn (Rental $rental) => [
                'start' => ($rental->status === 'active' ? $from : $rental->start_time)->format('Y-m-d H:i'),
                // Rental aktif tetap mengunci unit hingga statusnya berubah saat pengembalian.
                'end' => ($rental->status === 'active' ? $to : $rental->end_time)->format('Y-m-d H:i'),
            ]);

        $returnedTodayRanges = Rental::query()
            ->where('bike_id', $bikeId)
            ->where('status', 'completed')
            ->whereHas('rentalReturn', fn (Builder $query) => $query
                ->where('actual_return_time', '>=', $from->copy()->startOfDay())
                ->where('actual_return_time', '<', $to->copy()->startOfDay()))
            ->with('rentalReturn:id,rental_id,actual_return_time')
            ->get()
            ->map(fn (Rental $rental) => [
                'start' => $rental->rentalReturn->actual_return_time->copy()->startOfDay()->format('Y-m-d H:i'),
                'end' => $rental->rentalReturn->actual_return_time->copy()->addDay()->startOfDay()->format('Y-m-d H:i'),
            ]);

        return $blockingRanges->concat($returnedTodayRanges)->sortBy('start')->values()->all();
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
        // Status active berarti motor sudah diserahkan dan belum dikembalikan.
        // Abaikan end_time terjadwal sampai rental benar-benar ditutup.
        if ($this->blockingQuery($bikeId)->where('status', 'active')->exists()) {
            return [];
        }

        [$hour, $minute] = array_map('intval', explode(':', $time));

        $minDays = max(1, (int) config('rental.min_days'));
        $maxDays = (int) config('rental.max_days');

        $firstDay = $from->copy()->startOfDay();
        $windowEnd = $firstDay->copy()->addDays($windowDays + $maxDays + 1);

        $rentals = $this->blockingQuery($bikeId)
            ->where('end_time', '>', $firstDay)
            ->where('start_time', '<', $windowEnd)
            ->get(['start_time', 'end_time']);

        $returnDates = Rental::query()
            ->where('bike_id', $bikeId)
            ->where('status', 'completed')
            ->whereHas('rentalReturn', fn (Builder $query) => $query
                ->where('actual_return_time', '>=', $firstDay)
                ->where('actual_return_time', '<', $windowEnd))
            ->with('rentalReturn:id,rental_id,actual_return_time')
            ->get()
            ->map(fn (Rental $rental) => $rental->rentalReturn->actual_return_time->toDateString())
            ->all();

        $dates = [];

        for ($i = 0; $i < $windowDays; $i++) {
            $start = $firstDay->copy()->addDays($i)->setTime($hour, $minute);

            if ($start->lessThan(now())) {
                continue; // jam mulai sudah lewat
            }

            if (in_array($start->toDateString(), $returnDates, true)) {
                continue; // motor yang dikembalikan hari ini baru dapat disewa besok
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
            ->where(fn (Builder $query) => $query
                ->where(fn (Builder $query) => $query
                    ->whereIn('status', self::BLOCKING_STATUSES)
                    ->where($this->activeBlockingDeadlineQuery(...))
                    ->where(fn (Builder $query) => $query
                        ->where('status', 'active')
                        ->orWhere(fn (Builder $query) => $query
                            ->where('start_time', '<', $to)
                            ->where('end_time', '>', $from))))
                ->orWhere(fn (Builder $query) => $query
                    ->where('status', 'completed')
                    ->whereHas('rentalReturn', fn (Builder $query) => $query
                        ->where('actual_return_time', '>=', $from->copy()->startOfDay())
                        ->where('actual_return_time', '<', $from->copy()->addDay()->startOfDay()))));
    }

    /** Subquery unit yang sedang berada di tangan penyewa dan belum dikembalikan. */
    public function activeRentalBikeQuery(): Builder
    {
        return Rental::query()
            ->where('status', 'active')
            ->select('bike_id');
    }

    public function isActivelyRented(int $bikeId): bool
    {
        return $this->blockingQuery($bikeId)->where('status', 'active')->exists();
    }

    private function blockingQuery(int $bikeId): Builder
    {
        return Rental::query()
            ->where('bike_id', $bikeId)
            ->whereIn('status', self::BLOCKING_STATUSES)
            ->where($this->activeBlockingDeadlineQuery(...));
    }

    private function hasCompletedReturnOnDate(int $bikeId, CarbonInterface $start): bool
    {
        return Rental::query()
            ->where('bike_id', $bikeId)
            ->where('status', 'completed')
            ->whereHas('rentalReturn', fn (Builder $query) => $query
                ->where('actual_return_time', '>=', $start->copy()->startOfDay())
                ->where('actual_return_time', '<', $start->copy()->addDay()->startOfDay()))
            ->exists();
    }

    /** Pending pembayaran hanya mengunci slot sebelum tenggatnya. */
    private function activeBlockingDeadlineQuery(Builder $query): void
    {
        $query->where('status', '!=', 'pending_payment')
            ->orWhere(fn (Builder $query) => $query->where('status', 'pending_payment')->where('expires_at', '>', now()));
    }
}
