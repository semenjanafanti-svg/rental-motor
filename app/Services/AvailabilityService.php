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
     * Rentang waktu yang sudah terisi untuk kalender (Flatpickr).
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
