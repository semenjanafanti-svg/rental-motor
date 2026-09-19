<?php

namespace Tests\Feature;

use App\Models\Bike;
use App\Models\Rental;
use App\Models\User;
use App\Services\AvailabilityService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AvailabilityServiceTest extends TestCase
{
    use RefreshDatabase;

    /** PENGAMAN: RefreshDatabase menghapus semua tabel, jadi hanya boleh di SQLite in-memory. */
    protected function beforeRefreshingDatabase(): void
    {
        if (config('database.default') !== 'sqlite') {
            throw new \RuntimeException(
                'AvailabilityServiceTest hanya boleh berjalan di SQLite in-memory. '
                . 'Aktifkan DB_CONNECTION=sqlite dan DB_DATABASE=:memory: di phpunit.xml.'
            );
        }
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-09-19 08:00:00'));
    }

    private function bike(): Bike
    {
        return Bike::create([
            'name' => 'Vario 160', 'brand' => 'Honda', 'license_plate' => 'L 9999 ZZ',
            'category' => 'matic', 'cc' => 160, 'year' => 2023,
            'daily_rate' => 100000, 'hourly_rate' => 12000, 'status' => 'available',
        ]);
    }

    private function rental(Bike $bike, string $start, string $end, string $status, ?Carbon $expiresAt = null): Rental
    {
        return Rental::create([
            'booking_code' => 'BK-TEST-' . uniqid(),
            'user_id' => User::factory()->create()->id,
            'bike_id' => $bike->id,
            'start_time' => $start,
            'end_time' => $end,
            'total_hours' => 48,
            'hourly_rate_applied' => 12000,
            'daily_rate_applied' => 100000,
            'total_price' => 200000,
            'dp_amount' => 60000,
            'balance_amount' => 140000,
            'status' => $status,
            'expires_at' => $expiresAt,
        ]);
    }

    private function dates(Bike $bike, string $time): array
    {
        return app(AvailabilityService::class)->availableStartDates($bike->id, $time, now());
    }

    public function test_free_bike_offers_dates_capped_by_max_days(): void
    {
        $dates = $this->dates($this->bike(), '08:00');

        $this->assertSame(30, $dates['2026-09-19']); // jam mulai persis sekarang masih boleh
        $this->assertSame(30, $dates['2026-09-20']);
    }

    public function test_start_time_earlier_than_now_is_excluded_for_today_only(): void
    {
        $dates = $this->dates($this->bike(), '07:00');

        $this->assertArrayNotHasKey('2026-09-19', $dates);
        $this->assertArrayHasKey('2026-09-20', $dates);
    }

    public function test_existing_rental_limits_days_and_blocks_its_own_span(): void
    {
        $bike = $this->bike();
        $this->rental($bike, '2026-09-22 08:00', '2026-09-24 08:00', 'approved');

        $dates = $this->dates($bike, '08:00');

        $this->assertSame(3, $dates['2026-09-19']);            // sampai tepat sebelum sewa berikutnya
        $this->assertSame(1, $dates['2026-09-21']);
        $this->assertArrayNotHasKey('2026-09-22', $dates);      // mulai bersamaan dengan sewa lain
        $this->assertArrayNotHasKey('2026-09-23', $dates);      // jatuh di tengah sewa lain
        $this->assertSame(30, $dates['2026-09-24']);            // bersambung tepat setelah sewa selesai
    }

    public function test_start_time_that_does_not_fit_24_hours_before_next_rental_is_excluded(): void
    {
        $bike = $this->bike();
        $this->rental($bike, '2026-09-22 08:00', '2026-09-24 08:00', 'approved');

        $dates = $this->dates($bike, '12:00');

        $this->assertSame(2, $dates['2026-09-19']);
        $this->assertSame(1, $dates['2026-09-20']);
        $this->assertArrayNotHasKey('2026-09-21', $dates); // hanya 20 jam sebelum sewa berikutnya
    }

    public function test_expired_pending_payment_does_not_block_dates(): void
    {
        $bike = $this->bike();
        $this->rental($bike, '2026-09-22 08:00', '2026-09-24 08:00', 'pending_payment', now()->subMinute());

        $this->assertSame(30, $this->dates($bike, '08:00')['2026-09-22']);
    }

    public function test_cancelled_rental_does_not_block_dates(): void
    {
        $bike = $this->bike();
        $this->rental($bike, '2026-09-22 08:00', '2026-09-24 08:00', 'cancelled');

        $this->assertArrayHasKey('2026-09-23', $this->dates($bike, '08:00'));
    }
}
