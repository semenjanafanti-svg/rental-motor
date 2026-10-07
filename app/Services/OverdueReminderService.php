<?php

namespace App\Services;

use App\Models\Rental;
use Illuminate\Support\Facades\DB;

class OverdueReminderService
{
    /** Create one pending overdue reminder for each active rental past its return time. */
    public function createMissing(): int
    {
        $created = 0;

        Rental::query()
            ->where('status', 'active')
            ->where('end_time', '<=', now())
            ->whereDoesntHave('reminders', fn ($query) => $query->where('type', 'overdue'))
            ->select('id')
            ->chunkById(100, function ($rentals) use (&$created): void {
                foreach ($rentals as $rental) {
                    $created += DB::transaction(function () use ($rental): int {
                        $locked = Rental::query()->lockForUpdate()->find($rental->id);

                        if (! $locked || $locked->status !== 'active' || $locked->end_time->isFuture()) {
                            return 0;
                        }

                        if ($locked->reminders()->where('type', 'overdue')->exists()) {
                            return 0;
                        }

                        $locked->reminders()->create([
                            'type' => 'overdue',
                            'scheduled_at' => now(),
                            'status' => 'pending',
                        ]);

                        return 1;
                    });
                }
            });

        return $created;
    }
}
