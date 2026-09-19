<?php

namespace Tests\Feature;

use App\Exceptions\BikeNotAvailableException;
use App\Exceptions\InvalidBookingPeriodException;
use App\Exceptions\SlotNotAvailableException;
use App\Models\Bike;
use App\Models\Rental;
use App\Models\User;
use App\Services\BookingService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingServiceTest extends TestCase
{
    use RefreshDatabase;

    /**
     * PENGAMAN: RefreshDatabase menghapus semua tabel. Tes ini hanya boleh
     * berjalan di SQLite in-memory (lihat phpunit.xml), bukan di MySQL dev.
     */
    protected function beforeRefreshingDatabase(): void
    {
        if (config('database.default') !== 'sqlite') {
            throw new \RuntimeException(
                'BookingServiceTest hanya boleh berjalan di SQLite in-memory. '
                . 'Aktifkan DB_CONNECTION=sqlite dan DB_DATABASE=:memory: di phpunit.xml.'
            );
        }
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-09-19 08:00:00'));
    }

    private function service(): BookingService
    {
        return app(BookingService::class);
    }

    private function bike(array $override = []): Bike
    {
        return Bike::create($override + [
            'name' => 'Vario 160',
            'brand' => 'Honda',
            'license_plate' => 'L 9999 ZZ',
            'category' => 'matic',
            'cc' => 160,
            'year' => 2023,
            'daily_rate' => 100000,
            'hourly_rate' => 12000,
            'status' => 'available',
        ]);
    }

    private function data(Bike $bike, string $start, string $end): array
    {
        return [
            'bike_id' => $bike->id,
            'start_time' => $start,
            'end_time' => $end,
            'ktp_photo' => 'ktp/contoh.jpg',
            'sim_photo' => 'sim/contoh.jpg',
        ];
    }

    private function existingRental(
        Bike $bike,
        User $user,
        string $start,
        string $end,
        string $status,
        ?Carbon $expiresAt = null,
    ): Rental {
        static $n = 0;
        $n++;

        return Rental::create([
            'booking_code' => 'BK-TEST-' . $n,
            'user_id' => $user->id,
            'bike_id' => $bike->id,
            'start_time' => $start,
            'end_time' => $end,
            'total_hours' => 24,
            'hourly_rate_applied' => 12000,
            'daily_rate_applied' => 100000,
            'total_price' => 100000,
            'dp_amount' => 30000,
            'balance_amount' => 70000,
            'status' => $status,
            'expires_at' => $expiresAt,
        ]);
    }

    public function test_creates_rental_verification_and_dp_payment(): void
    {
        $user = User::factory()->create();
        $bike = $this->bike();

        $rental = $this->service()->create($user, $this->data($bike, '2026-09-20 08:00', '2026-09-21 08:00'));

        $this->assertSame('BK-20260919-0001', $rental->booking_code);
        $this->assertSame('pending_payment', $rental->status);
        $this->assertSame('unpaid', $rental->payment_status);
        $this->assertSame(24, $rental->total_hours);
        $this->assertEquals(100000, $rental->total_price);
        $this->assertEquals(30000, $rental->dp_amount);
        $this->assertEquals(70000, $rental->balance_amount);
        $this->assertTrue($rental->expires_at->equalTo(now()->addMinutes(30)));

        $this->assertDatabaseHas('verifications', ['rental_id' => $rental->id, 'status' => 'pending']);
        $this->assertDatabaseHas('payments', [
            'rental_id' => $rental->id,
            'order_id' => 'BK-20260919-0001-DP',
            'type' => 'dp',
            'payment_status' => 'pending',
        ]);
    }

    public function test_rate_is_snapshotted_at_booking_time(): void
    {
        $bike = $this->bike();
        $rental = $this->service()->create(User::factory()->create(), $this->data($bike, '2026-09-20 08:00', '2026-09-21 08:00'));

        $bike->update(['daily_rate' => 999999, 'hourly_rate' => 99999]);

        $this->assertEquals(100000, $rental->fresh()->daily_rate_applied);
        $this->assertEquals(12000, $rental->fresh()->hourly_rate_applied);
    }

    public function test_rejects_duration_below_minimum(): void
    {
        $this->expectException(InvalidBookingPeriodException::class);

        $this->service()->create(
            User::factory()->create(),
            $this->data($this->bike(), '2026-09-20 08:00', '2026-09-20 19:59')
        );
    }

    public function test_accepts_exactly_minimum_duration(): void
    {
        $rental = $this->service()->create(
            User::factory()->create(),
            $this->data($this->bike(), '2026-09-20 08:00', '2026-09-20 20:00')
        );

        $this->assertSame(12, $rental->total_hours);
    }

    public function test_rejects_start_time_in_the_past(): void
    {
        $this->expectException(InvalidBookingPeriodException::class);

        $this->service()->create(
            User::factory()->create(),
            $this->data($this->bike(), '2026-09-18 08:00', '2026-09-19 08:00')
        );
    }

    public function test_rejects_end_before_start(): void
    {
        $this->expectException(InvalidBookingPeriodException::class);

        $this->service()->create(
            User::factory()->create(),
            $this->data($this->bike(), '2026-09-21 08:00', '2026-09-20 08:00')
        );
    }

    public function test_rejects_overlapping_booking(): void
    {
        $user = User::factory()->create();
        $bike = $this->bike();
        $this->existingRental($bike, $user, '2026-09-22 08:00', '2026-09-23 08:00', 'approved');

        $this->expectException(SlotNotAvailableException::class);

        $this->service()->create($user, $this->data($bike, '2026-09-22 20:00', '2026-09-23 20:00'));
    }

    public function test_allows_back_to_back_booking(): void
    {
        $user = User::factory()->create();
        $bike = $this->bike();
        $this->existingRental($bike, $user, '2026-09-22 08:00', '2026-09-23 08:00', 'active');

        $rental = $this->service()->create($user, $this->data($bike, '2026-09-23 08:00', '2026-09-24 08:00'));

        $this->assertSame('pending_payment', $rental->status);
    }

    public function test_active_pending_payment_blocks_but_expired_one_does_not(): void
    {
        $user = User::factory()->create();
        $bike = $this->bike();

        // Kedaluwarsa 1 menit lalu: tidak menghalangi
        $this->existingRental($bike, $user, '2026-09-22 08:00', '2026-09-23 08:00', 'pending_payment', now()->subMinute());

        $rental = $this->service()->create($user, $this->data($bike, '2026-09-22 08:00', '2026-09-23 08:00'));
        $this->assertSame('pending_payment', $rental->status);

        // Booking baru tadi masih berlaku: percobaan berikutnya harus ditolak
        $this->expectException(SlotNotAvailableException::class);
        $this->service()->create($user, $this->data($bike, '2026-09-22 08:00', '2026-09-23 08:00'));
    }

    public function test_cancelled_rental_does_not_block(): void
    {
        $user = User::factory()->create();
        $bike = $this->bike();
        $this->existingRental($bike, $user, '2026-09-22 08:00', '2026-09-23 08:00', 'cancelled');

        $rental = $this->service()->create($user, $this->data($bike, '2026-09-22 08:00', '2026-09-23 08:00'));

        $this->assertSame('pending_payment', $rental->status);
    }

    public function test_rejects_bike_under_maintenance(): void
    {
        $this->expectException(BikeNotAvailableException::class);

        $this->service()->create(
            User::factory()->create(),
            $this->data($this->bike(['status' => 'maintenance']), '2026-09-20 08:00', '2026-09-21 08:00')
        );
    }

    public function test_failed_booking_creates_no_records(): void
    {
        try {
            $this->service()->create(
                User::factory()->create(),
                $this->data($this->bike(['status' => 'inactive']), '2026-09-20 08:00', '2026-09-21 08:00')
            );
        } catch (BikeNotAvailableException) {
        }

        $this->assertDatabaseCount('rentals', 0);
        $this->assertDatabaseCount('verifications', 0);
        $this->assertDatabaseCount('payments', 0);
    }
}
