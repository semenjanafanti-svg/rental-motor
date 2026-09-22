<?php

namespace Tests\Feature;

use App\Exceptions\PaymentException;
use App\Models\Bike;
use App\Models\Rental;
use App\Models\User;
use App\Services\HandoverService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HandoverServiceTest extends TestCase
{
    use RefreshDatabase;

    /** PENGAMAN: RefreshDatabase menghapus semua tabel, jadi hanya boleh di SQLite in-memory. */
    protected function beforeRefreshingDatabase(): void
    {
        if (config('database.default') !== 'sqlite') {
            throw new \RuntimeException(
                'HandoverServiceTest hanya boleh berjalan di SQLite in-memory. '
                . 'Aktifkan DB_CONNECTION=sqlite dan DB_DATABASE=:memory: di phpunit.xml.'
            );
        }
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-09-20 08:00:00'));
    }

    private function service(): HandoverService
    {
        return app(HandoverService::class);
    }

    private function bike(): Bike
    {
        return Bike::create([
            'name' => 'Vario 160', 'brand' => 'Honda', 'license_plate' => 'L 9999 ZZ',
            'category' => 'matic', 'cc' => 160, 'year' => 2023,
            'daily_rate' => 100000, 'hourly_rate' => 12000, 'status' => 'available',
        ]);
    }

    private function rental(string $status, array $override = []): Rental
    {
        return Rental::create($override + [
            'booking_code' => 'BK-TEST-' . uniqid(),
            'user_id' => User::factory()->create()->id,
            'bike_id' => $this->bike()->id,
            'start_time' => '2026-09-20 08:00',
            'end_time' => '2026-09-21 08:00',
            'total_hours' => 24,
            'hourly_rate_applied' => 12000,
            'daily_rate_applied' => 100000,
            'total_price' => 100000,
            'dp_amount' => 30000,
            'balance_amount' => 70000,
            'payment_status' => 'dp_paid',
            'status' => $status,
        ]);
    }

    public function test_check_in_records_balance_payment_and_activates_rental(): void
    {
        $rental = $this->rental('approved');
        $admin = User::factory()->create();

        $result = $this->service()->checkIn($rental, $admin, 'cash');

        $this->assertSame('active', $result->status);
        $this->assertSame('fully_paid', $result->payment_status);
        $this->assertEquals(0, $result->balance_amount);
        $this->assertNotNull($result->picked_up_at);
        $this->assertSame($admin->id, $result->handed_over_by);

        $this->assertDatabaseHas('payments', [
            'rental_id' => $rental->id,
            'order_id' => $rental->booking_code . '-BAL',
            'type' => 'balance',
            'gross_amount' => 70000,
            'payment_status' => 'settlement',
        ]);

        $this->assertDatabaseHas('rental_reminders', ['rental_id' => $rental->id, 'type' => 'pickup_confirmation']);
        $this->assertDatabaseHas('rental_reminders', ['rental_id' => $rental->id, 'type' => 'return_2h']);
        $this->assertDatabaseHas('rental_reminders', ['rental_id' => $rental->id, 'type' => 'return_30m']);
    }

    public function test_check_in_skips_balance_payment_when_already_zero(): void
    {
        $rental = $this->rental('approved', ['balance_amount' => 0]);

        $this->service()->checkIn($rental, User::factory()->create());

        $this->assertDatabaseMissing('payments', ['rental_id' => $rental->id, 'type' => 'balance']);
    }

    public function test_check_in_rejects_wrong_status(): void
    {
        $this->expectException(PaymentException::class);

        $this->service()->checkIn($this->rental('pending_verification'), User::factory()->create());
    }

    public function test_check_out_on_time_has_no_late_fee(): void
    {
        $rental = $this->rental('active');
        $admin = User::factory()->create();

        $result = $this->service()->checkOut(
            $rental, $admin, Carbon::parse('2026-09-21 07:55'), 0, 0, null, 'cash'
        );

        $this->assertSame('completed', $result->status);
        $this->assertDatabaseHas('rental_returns', [
            'rental_id' => $rental->id, 'late_hours' => 0, 'late_fee' => 0,
        ]);
        $this->assertDatabaseMissing('payments', ['rental_id' => $rental->id, 'type' => 'fine']);
    }

    public function test_check_out_within_tolerance_has_no_late_fee(): void
    {
        $rental = $this->rental('active'); // end_time 2026-09-21 08:00

        $toleranceMinutes = (int) config('rental.late_tolerance_minutes');
        $returnTime = Carbon::parse('2026-09-21 08:00')->addMinutes(max(0, $toleranceMinutes - 5));

        $result = $this->service()->checkOut(
            $rental, User::factory()->create(), $returnTime, 0, 0, null
        );

        $this->assertDatabaseHas('rental_returns', [
            'rental_id' => $rental->id, 'late_hours' => 0, 'late_fee' => 0,
        ]);
        $this->assertDatabaseMissing('payments', ['rental_id' => $rental->id, 'type' => 'fine']);
    }

    public function test_check_out_after_tolerance_charges_late_fee_per_rounded_hour(): void
    {
        $rental = $this->rental('active'); // hourly_rate_applied = 12000, end_time 08:00
        $admin = User::factory()->create();

        // Lewat toleransi + 1 jam 10 menit, apa pun nilai toleransi di config, supaya
        // test ini tidak bergantung pada angka toleransi tertentu.
        $toleranceMinutes = (int) config('rental.late_tolerance_minutes');
        $returnTime = Carbon::parse('2026-09-21 08:00')->addMinutes($toleranceMinutes + 70);

        // Dibulatkan ke atas ke jam penuh dari total keterlambatan (bukan hanya kelebihan toleransi)
        $expectedLateHours = (int) ceil(($toleranceMinutes + 70) * 60 / 3600);
        $expectedLateFee = $expectedLateHours * 12000;

        $result = $this->service()->checkOut(
            $rental, $admin, $returnTime, 50000, 10000, 'Baret kecil di bodi', 'cash'
        );

        $this->assertSame('completed', $result->status);
        $this->assertDatabaseHas('rental_returns', [
            'rental_id' => $rental->id,
            'late_hours' => $expectedLateHours,
            'late_fee' => $expectedLateFee,
            'damage_fee' => 50000,
            'fuel_fee' => 10000,
            'condition_notes' => 'Baret kecil di bodi',
            'checked_by' => $admin->id,
        ]);
        $this->assertDatabaseHas('payments', [
            'rental_id' => $rental->id,
            'order_id' => $rental->booking_code . '-FINE',
            'type' => 'fine',
            'gross_amount' => $expectedLateFee + 50000 + 10000,
            'payment_status' => 'settlement',
        ]);
    }

    public function test_check_out_marks_pending_reminders_as_skipped(): void
    {
        $rental = $this->rental('active');
        $rental->reminders()->create([
            'type' => 'return_2h', 'scheduled_at' => now(), 'status' => 'pending',
        ]);

        $this->service()->checkOut($rental, User::factory()->create(), now(), 0, 0, null);

        $this->assertDatabaseHas('rental_reminders', [
            'rental_id' => $rental->id, 'type' => 'return_2h', 'status' => 'skipped',
        ]);
    }

    public function test_check_out_rejects_wrong_status(): void
    {
        $this->expectException(PaymentException::class);

        $this->service()->checkOut($this->rental('approved'), User::factory()->create(), now(), 0, 0, null);
    }

    public function test_check_out_rejects_duplicate_return(): void
    {
        $rental = $this->rental('active');
        $admin = User::factory()->create();

        // Ganti status manual jadi active lagi untuk simulasi percobaan check-out kedua
        $this->service()->checkOut($rental, $admin, now(), 0, 0, null);
        $rental->update(['status' => 'active']);

        $this->expectException(PaymentException::class);
        $this->service()->checkOut($rental, $admin, now(), 0, 0, null);
    }
}