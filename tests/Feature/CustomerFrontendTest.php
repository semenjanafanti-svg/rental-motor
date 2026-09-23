<?php

namespace Tests\Feature;

use App\Models\Bike;
use App\Models\Rental;
use App\Models\RentalReturn;
use App\Models\User;
use App\Models\Verification;
use App\Services\BookingService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CustomerFrontendTest extends TestCase
{
    use RefreshDatabase;

    /** PENGAMAN: RefreshDatabase menghapus semua tabel, jadi hanya boleh di SQLite in-memory. */
    protected function beforeRefreshingDatabase(): void
    {
        if (config('database.default') !== 'sqlite') {
            throw new \RuntimeException(
                'CustomerFrontendTest hanya boleh berjalan di SQLite in-memory. '
                . 'Aktifkan DB_CONNECTION=sqlite dan DB_DATABASE=:memory: di phpunit.xml.'
            );
        }
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite(); // tanpa perlu npm run build saat tes
        $this->travelTo(Carbon::parse('2026-09-19 08:00:00'));
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

    /** fresh() memuat default kolom dari database (role = customer). */
    private function customer(): User
    {
        return User::factory()->create()->fresh();
    }

    private function bookingData(Bike $bike): array
    {
        return [
            'bike_id' => $bike->id,
            'start_date' => '2026-09-20',
            'start_time' => '08:00',
            'end_date' => '2026-09-21',
            'ktp_photo' => 'x',
            'sim_photo' => 'x',
        ];
    }

    private function checkoutPayload(array $override = []): array
    {
        return $override + [
            'start_date' => '2026-09-20',
            'start_time' => '08:00',
            'end_date' => '2026-09-21',
            'ktp_photo' => UploadedFile::fake()->image('ktp.jpg'),
            'sim_photo' => UploadedFile::fake()->image('sim.png'),
        ];
    }

    public function test_catalog_shows_only_available_bikes_and_filters_by_category(): void
    {
        $this->bike(['name' => 'Vario Aktif', 'license_plate' => 'L 1 A']);
        $this->bike(['name' => 'Beat Servis', 'license_plate' => 'L 2 A', 'status' => 'maintenance']);
        $this->bike(['name' => 'R15 Sport', 'license_plate' => 'L 3 A', 'category' => 'sport']);

        $this->get('/motor')
            ->assertOk()
            ->assertSee('Vario Aktif')
            ->assertSee('R15 Sport')
            ->assertDontSee('Beat Servis');

        $this->get('/motor?category=sport')
            ->assertOk()
            ->assertSee('R15 Sport')
            ->assertDontSee('Vario Aktif');
    }

    public function test_catalog_filters_by_max_daily_price(): void
    {
        $this->bike(['name' => 'Murah', 'license_plate' => 'L 1 A', 'daily_rate' => 75000]);
        $this->bike(['name' => 'Mahal', 'license_plate' => 'L 2 A', 'daily_rate' => 250000]);

        $this->get('/motor?max_price=100000')
            ->assertOk()
            ->assertSee('Murah')
            ->assertDontSee('Mahal');
    }

    public function test_guest_is_redirected_to_login_for_checkout(): void
    {
        $bike = $this->bike();

        $this->get("/motor/{$bike->id}/checkout")->assertRedirect('/login');
    }

    public function test_staff_cannot_open_checkout(): void
    {
        $bike = $this->bike();
        $admin = User::factory()->create();
        $admin->forceFill(['role' => 'admin'])->save();

        $this->actingAs($admin)->get("/motor/{$bike->id}/checkout")->assertForbidden();
    }

    public function test_quote_returns_period_and_price(): void
    {
        $bike = $this->bike();

        $this->getJson("/motor/{$bike->id}/quote?start_date=2026-09-20&start_time=08:00&end_date=2026-09-22")
            ->assertOk()
            ->assertJson([
                'ok' => true,
                'days' => 2,
                'total_hours' => 48,
                'total_price' => 200000,
                'dp_amount' => 60000,
                'balance_amount' => 140000,
                'start_at' => '2026-09-20 08:00',
                'end_at' => '2026-09-22 08:00',
            ]);
    }

    public function test_quote_rejects_same_day_return(): void
    {
        $bike = $this->bike();

        $this->getJson("/motor/{$bike->id}/quote?start_date=2026-09-20&start_time=08:00&end_date=2026-09-20")
            ->assertStatus(422)
            ->assertJson(['ok' => false]);
    }

    public function test_dates_endpoint_lists_available_start_dates_with_max_days(): void
    {
        $bike = $this->bike();
        app(BookingService::class)->create($this->customer(), $this->bookingData($bike)); // 20-21 Sep 08:00

        $dates = $this->getJson("/motor/{$bike->id}/available-dates?start_time=08:00")
            ->assertOk()
            ->json('dates');

        $this->assertSame(1, $dates['2026-09-19']);            // hanya muat 1 hari sebelum sewa 20 Sep
        $this->assertArrayNotHasKey('2026-09-20', $dates);
        $this->assertSame(30, $dates['2026-09-21']);           // bersambung setelah sewa selesai
    }

    public function test_dates_endpoint_rejects_invalid_time(): void
    {
        $bike = $this->bike();

        $this->getJson("/motor/{$bike->id}/available-dates?start_time=pagi")->assertStatus(422);
    }

    public function test_availability_lists_booked_ranges_without_customer_data(): void
    {
        $bike = $this->bike();
        app(BookingService::class)->create($this->customer(), $this->bookingData($bike));

        $this->getJson("/motor/{$bike->id}/availability")
            ->assertOk()
            ->assertExactJson(['ranges' => [['start' => '2026-09-20 08:00', 'end' => '2026-09-21 08:00']]]);
    }

    public function test_customer_can_checkout_and_documents_are_stored_privately(): void
    {
        Storage::fake('local');
        $bike = $this->bike();

        $this->actingAs($this->customer())
            ->post("/motor/{$bike->id}/checkout", $this->checkoutPayload())
            ->assertSessionHasNoErrors();

        $rental = Rental::firstOrFail();
        $this->assertSame('pending_payment', $rental->status);
        $this->assertSame('2026-09-21 08:00', $rental->end_time->format('Y-m-d H:i'));
        $this->assertDatabaseHas('payments', ['rental_id' => $rental->id, 'type' => 'dp', 'payment_status' => 'pending']);

        $verification = Verification::firstOrFail();
        Storage::disk('local')->assertExists($verification->ktp_photo);
        Storage::disk('local')->assertExists($verification->sim_photo);
    }

    public function test_checkout_rejects_non_image_document(): void
    {
        Storage::fake('local');
        $bike = $this->bike();

        $this->actingAs($this->customer())
            ->post("/motor/{$bike->id}/checkout", $this->checkoutPayload([
                'ktp_photo' => UploadedFile::fake()->create('ktp.pdf', 100, 'application/pdf'),
            ]))
            ->assertSessionHasErrors('ktp_photo');

        $this->assertDatabaseCount('rentals', 0);
    }

    public function test_checkout_rejects_return_date_not_after_start_date(): void
    {
        Storage::fake('local');
        $bike = $this->bike();

        $this->actingAs($this->customer())
            ->post("/motor/{$bike->id}/checkout", $this->checkoutPayload(['end_date' => '2026-09-20']))
            ->assertSessionHasErrors('end_date');

        $this->assertDatabaseCount('rentals', 0);
        $this->assertEmpty(Storage::disk('local')->allFiles());
    }

    public function test_conflicting_checkout_shows_error_and_removes_uploaded_files(): void
    {
        Storage::fake('local');
        $bike = $this->bike();
        app(BookingService::class)->create($this->customer(), $this->bookingData($bike));

        $this->actingAs($this->customer())
            ->post("/motor/{$bike->id}/checkout", $this->checkoutPayload())
            ->assertSessionHasErrors('booking');

        $this->assertEmpty(Storage::disk('local')->allFiles());
        $this->assertDatabaseCount('rentals', 1);
    }

    public function test_customer_sees_only_own_rentals(): void
    {
        $bike = $this->bike();
        $owner = $this->customer();
        $other = $this->customer();
        $rental = app(BookingService::class)->create($owner, $this->bookingData($bike));

        $this->actingAs($owner)->get('/riwayat')->assertOk()->assertSee($rental->booking_code);
        $this->actingAs($owner)->get("/riwayat/{$rental->id}")->assertOk()->assertSee($rental->booking_code);

        $this->actingAs($other)->get('/riwayat')->assertOk()->assertDontSee($rental->booking_code);
        $this->actingAs($other)->get("/riwayat/{$rental->id}")->assertNotFound();
    }

    public function test_customer_can_download_receipt_only_after_completed_return(): void
    {
        $customer = $this->customer();
        $rental = app(BookingService::class)->create($customer, $this->bookingData($this->bike()));
        $rental->payments()->where('type', 'dp')->update(['payment_status' => 'settlement', 'paid_at' => now()]);
        $rental->update(['status' => 'completed', 'payment_status' => 'fully_paid', 'balance_amount' => 0]);
        RentalReturn::create([
            'rental_id' => $rental->id,
            'actual_return_time' => Carbon::parse('2026-09-21 08:00'),
            'late_hours' => 0,
            'late_fee' => 0,
            'damage_fee' => 0,
            'fuel_fee' => 0,
            'checked_by' => $customer->id,
        ]);

        $this->actingAs($customer)
            ->get("/riwayat/{$rental->id}/nota")
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf')
            ->assertSee('%PDF-1.4', false);
    }

    public function test_customer_cannot_download_receipt_for_no_show_rental(): void
    {
        $customer = $this->customer();
        $rental = app(BookingService::class)->create($customer, $this->bookingData($this->bike()));
        $rental->update(['status' => 'no_show']);

        $this->actingAs($customer)->get("/riwayat/{$rental->id}/nota")->assertNotFound();
    }
}
