<?php

namespace App\Services;

use App\Exceptions\PaymentException;
use App\Models\Payment;
use App\Models\Rental;
use App\Models\RentalReminder;
use App\Models\RentalReturn;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Serah terima (check-in) dan pengembalian (check-out) motor.
 * Pelunasan dan denda dibayar/dicatat langsung oleh admin di lokasi (tunai atau QRIS),
 * tidak lewat Midtrans, konsisten dengan alur DP di PaymentService.
 */
class HandoverService
{
    /**
     * Catat pelunasan + serah terima unit. Rental harus berstatus 'approved'.
     * Balance harus lunas sebelum status berpindah ke 'active' (check-in diblokir selama balance > 0).
     */
    public function checkIn(Rental $rental, User $admin, string $method = 'cash'): Rental
    {
        $markedNoShow = false;

        $checkedInRental = DB::transaction(function () use ($rental, $admin, $method, &$markedNoShow) {
            $locked = Rental::lockForUpdate()->findOrFail($rental->id);

            if ($locked->status !== 'approved') {
                throw new PaymentException('Pesanan ini tidak sedang menunggu serah terima.');
            }

            if (! $locked->isPickupPeriodOpen()) {
                throw new PaymentException('Pelunasan dan check-in baru bisa dilakukan saat waktu pengambilan dimulai.');
            }

            $pickupDeadline = $locked->start_time
                ->copy()
                ->addMinutes((int) config('rental.no_show_tolerance_minutes'));

            if ($pickupDeadline->lessThanOrEqualTo(now())) {
                $locked->update([
                    'status' => 'no_show',
                    'cancelled_reason' => 'Penyewa tidak datang mengambil motor sampai batas toleransi.',
                ]);
                $markedNoShow = true;

                return $locked;
            }

            if ((float) $locked->balance_amount > 0) {
                Payment::create([
                    'rental_id' => $locked->id,
                    'order_id' => $locked->booking_code.'-BAL',
                    'type' => 'balance',
                    'method' => $method,
                    'gross_amount' => $locked->balance_amount,
                    'payment_status' => 'settlement',
                    'paid_at' => now(),
                    'received_by' => $admin->id,
                ]);
            }

            $locked->update([
                'payment_status' => 'fully_paid',
                'balance_amount' => 0,
                'picked_up_at' => now(),
                'handed_over_by' => $admin->id,
                'status' => 'active',
            ]);

            $this->scheduleReminders($locked);

            return $locked;
        });

        if ($markedNoShow) {
            throw new PaymentException('Batas pengambilan sudah lewat. Pesanan ditandai tidak hadir dan check-in tidak dapat dilakukan.');
        }

        return $checkedInRental;
    }

    /**
     * Catat pengembalian unit. Rental harus berstatus 'active' (termasuk yang sudah overdue,
     * karena status overdue hanya diturunkan, bukan disimpan).
     * Denda telat dihitung otomatis; kerusakan dan bensin diinput manual admin.
     */
    public function checkOut(
        Rental $rental,
        User $admin,
        Carbon $actualReturnTime,
        float $damageFee,
        float $fuelFee,
        ?string $conditionNotes,
        string $method = 'cash',
    ): Rental {
        return DB::transaction(function () use ($rental, $admin, $actualReturnTime, $damageFee, $fuelFee, $conditionNotes, $method) {
            $locked = Rental::lockForUpdate()->findOrFail($rental->id);

            if ($locked->status !== 'active') {
                throw new PaymentException('Pesanan ini tidak sedang berstatus disewa.');
            }

            if ($locked->rentalReturn()->exists()) {
                throw new PaymentException('Pengembalian untuk pesanan ini sudah dicatat.');
            }

            $fees = $this->estimateReturnFees($locked, $actualReturnTime, $damageFee, $fuelFee);
            $totalFee = $fees['total_fee'];

            RentalReturn::create([
                'rental_id' => $locked->id,
                'actual_return_time' => $actualReturnTime,
                'late_hours' => $fees['late_hours'],
                'late_fee' => $fees['late_fee'],
                'damage_fee' => $fees['damage_fee'],
                'fuel_fee' => $fees['fuel_fee'],
                'condition_notes' => $conditionNotes,
                'checked_by' => $admin->id,
            ]);

            if ($totalFee > 0) {
                Payment::create([
                    'rental_id' => $locked->id,
                    'order_id' => $locked->booking_code.'-FINE',
                    'type' => 'fine',
                    'method' => $method,
                    'gross_amount' => $totalFee,
                    'payment_status' => 'settlement',
                    'paid_at' => now(),
                    'received_by' => $admin->id,
                ]);
            }

            $locked->update(['status' => 'completed']);

            // Reminder yang belum terkirim (return_30m/overdue) sudah tidak relevan.
            $locked->reminders()->where('status', 'pending')->update(['status' => 'skipped']);

            return $locked;
        });
    }

    /**
     * Hitung rincian yang sama untuk pratinjau admin dan pencatatan pengembalian.
     *
     * @return array{late_hours:int, late_fee:float, damage_fee:float, fuel_fee:float, total_fee:float}
     */
    public function estimateReturnFees(
        Rental $rental,
        Carbon $actualReturnTime,
        float $damageFee = 0,
        float $fuelFee = 0,
    ): array {
        [$lateHours, $lateFee] = $this->calculateLateFee($rental, $actualReturnTime);
        $damageFee = max(0, $damageFee);
        $fuelFee = max(0, $fuelFee);

        return [
            'late_hours' => $lateHours,
            'late_fee' => $lateFee,
            'damage_fee' => $damageFee,
            'fuel_fee' => $fuelFee,
            'total_fee' => $lateFee + $damageFee + $fuelFee,
        ];
    }

    /** @return array{0:int, 1:float} [late_hours, late_fee] */
    private function calculateLateFee(Rental $rental, Carbon $actualReturnTime): array
    {
        if ($actualReturnTime->lessThanOrEqualTo($rental->end_time)) {
            return [0, 0.0];
        }

        $toleranceSeconds = ((int) config('rental.late_tolerance_minutes')) * 60;
        // Carbon v3 mengembalikan selisih bertanda secara default. Untuk waktu
        // pengembalian yang lebih lambat kita butuh nilai absolut agar denda
        // tidak keliru menjadi nol.
        $lateSeconds = $actualReturnTime->diffInSeconds($rental->end_time, true);

        if ($lateSeconds <= $toleranceSeconds) {
            return [0, 0.0];
        }

        $lateHours = (int) ceil($lateSeconds / 3600);

        return [$lateHours, $lateHours * (float) $rental->hourly_rate_applied];
    }

    private function scheduleReminders(Rental $rental): void
    {
        $reminders = [
            'pickup_confirmation' => now(),
            'return_30m' => $rental->end_time->copy()->subMinutes(30),
        ];

        foreach ($reminders as $type => $scheduledAt) {
            RentalReminder::create([
                'rental_id' => $rental->id,
                'type' => $type,
                'scheduled_at' => $scheduledAt,
                'status' => 'pending',
            ]);
        }
    }
}
