<?php

namespace Tests\Feature;

use App\Models\Bike;
use App\Models\Rental;
use App\Models\User;
use App\Services\BookingService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class MidtransPaymentTest extends TestCase
{
    use RefreshDatabase;

    /** PENGAMAN: RefreshDatabase menghapus semua tabel, jadi hanya boleh di SQLite in-memory. */
    protected function beforeRefreshingDatabase(): void
    {
        if (config('database.default') !== 'sqlite') {
            throw new \RuntimeException(
                'MidtransPaymentTest hanya boleh berjalan di SQLite in-memory. '
                . 'Aktifkan DB_CONNECTION=sqlite dan DB_DATABASE=:memory: di phpunit.xml.'
            );
        }
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->travelTo(Carbon::parse('2026-09-19 08:00:00'));

        config([
            'midtrans.server_key' => 'SB-Mid-server-test',
            'midtrans.client_key' => 'SB-Mid-client-test',
        ]);
    }

    /** fresh() memuat default kolom dari database (role = customer). */
    private function customer(): User
    {
        return User::factory()->create()->fresh();
    }

    /** Sewa 1 hari: total Rp100.000, DP Rp30.000, batas bayar 30 menit. */
    private function rentalFor(User $user): Rental
    {
        $bike = Bike::create([
            'name' => 'Vario 160', 'brand' => 'Honda', 'license_plate' => 'L 9999 ZZ',
            'category' => 'matic', 'cc' => 160, 'year' => 2023,
            'daily_rate' => 100000, 'hourly_rate' => 12000, 'status' => 'available',
        ]);

        return app(BookingService::class)->create($user, [
            'bike_id' => $bike->id,
            'start_date' => '2026-09-20',
            'start_time' => '08:00',
            'end_date' => '2026-09-21',
            'ktp_photo' => 'x',
            'sim_photo' => 'x',
        ]);
    }

    /** Payload notifikasi Midtrans lengkap dengan signature_key yang valid. */
    private function notification(Rental $rental, string $status, array $override = []): array
    {
        $data = $override + [
            'transaction_time' => '2026-09-19 08:05:00',
            'transaction_status' => $status,
            'transaction_id' => 'trx-123',
            'status_message' => 'midtrans payment notification',
            'status_code' => '200',
            'payment_type' => 'qris',
            'order_id' => $rental->payments()->firstOrFail()->order_id,
            'gross_amount' => '30000.00',
            'fraud_status' => 'accept',
            'currency' => 'IDR',
        ];

        $data['signature_key'] = hash(
            'sha512',
            $data['order_id'] . $data['status_code'] . $data['gross_amount'] . config('midtrans.server_key')
        );

        return $data;
    }

    // ---------------------------------------------------------------- Snap token

    public function test_snap_token_is_created_once_and_reused(): void
    {
        Http::fake(['app.sandbox.midtrans.com/*' => Http::response(['token' => 'tok-123', 'redirect_url' => 'https://example.test/pay'], 201)]);

        $owner = $this->customer();
        $rental = $this->rentalFor($owner);

        $this->actingAs($owner)->postJson("/riwayat/{$rental->id}/bayar")
            ->assertOk()
            ->assertJson(['token' => 'tok-123']);

        $this->actingAs($owner)->postJson("/riwayat/{$rental->id}/bayar")
            ->assertOk()
            ->assertJson(['token' => 'tok-123']);

        Http::assertSentCount(1);
        Http::assertSent(function (HttpRequest $request) use ($rental) {
            return $request->hasHeader('Authorization')
                && $request['transaction_details']['order_id'] === $rental->booking_code . '-DP'
                && $request['transaction_details']['gross_amount'] === 30000
                && $request['expiry']['duration'] === 30;
        });

        $this->assertDatabaseHas('payments', ['rental_id' => $rental->id, 'snap_token' => 'tok-123']);
    }

    public function test_other_customer_cannot_request_token(): void
    {
        Http::fake();

        $rental = $this->rentalFor($this->customer());

        $this->actingAs($this->customer())->postJson("/riwayat/{$rental->id}/bayar")->assertNotFound();

        Http::assertNothingSent();
    }

    public function test_expired_rental_cannot_be_paid(): void
    {
        Http::fake();

        $owner = $this->customer();
        $rental = $this->rentalFor($owner);

        $this->travel(31)->minutes();

        $this->actingAs($owner)->postJson("/riwayat/{$rental->id}/bayar")->assertStatus(422);

        Http::assertNothingSent();
    }

    public function test_midtrans_rejection_returns_bad_gateway_without_saving_token(): void
    {
        Http::fake(['app.sandbox.midtrans.com/*' => Http::response(['error_messages' => ['ditolak']], 400)]);

        $owner = $this->customer();
        $rental = $this->rentalFor($owner);

        $this->actingAs($owner)->postJson("/riwayat/{$rental->id}/bayar")->assertStatus(502);

        $this->assertNull($rental->payments()->first()->snap_token);
    }

    // ---------------------------------------------------------------- Webhook

    public function test_settlement_notification_marks_dp_as_paid(): void
    {
        $rental = $this->rentalFor($this->customer());

        $this->postJson('/midtrans/notification', $this->notification($rental, 'settlement'))->assertOk();

        $payment = $rental->payments()->first();
        $this->assertSame('settlement', $payment->payment_status);
        $this->assertSame('trx-123', $payment->transaction_id);
        $this->assertSame('qris', $payment->payment_type);
        $this->assertNotNull($payment->paid_at);

        $rental->refresh();
        $this->assertSame('pending_verification', $rental->status);
        $this->assertSame('dp_paid', $rental->payment_status);
    }

    public function test_notification_is_idempotent_and_final_status_is_not_overwritten(): void
    {
        $rental = $this->rentalFor($this->customer());
        $payload = $this->notification($rental, 'settlement');

        $this->postJson('/midtrans/notification', $payload)->assertOk();
        $paidAt = $rental->payments()->first()->paid_at;

        $this->travel(1)->hours();

        // Notifikasi yang sama datang lagi
        $this->postJson('/midtrans/notification', $payload)->assertOk();
        // Notifikasi kedaluwarsa yang datang setelah settlement tidak boleh mengubah apa pun
        $this->postJson('/midtrans/notification', $this->notification($rental, 'expire', ['status_code' => '407']))->assertOk();

        $payment = $rental->payments()->first();
        $this->assertSame('settlement', $payment->payment_status);
        $this->assertTrue($payment->paid_at->equalTo($paidAt));
        $this->assertSame('pending_verification', $rental->fresh()->status);
    }

    public function test_invalid_signature_is_rejected(): void
    {
        $rental = $this->rentalFor($this->customer());
        $payload = $this->notification($rental, 'settlement');
        $payload['signature_key'] = 'salah';

        $this->postJson('/midtrans/notification', $payload)->assertForbidden();

        $this->assertSame('pending', $rental->payments()->first()->payment_status);
        $this->assertSame('pending_payment', $rental->fresh()->status);
    }

    public function test_notification_without_signature_is_rejected(): void
    {
        $rental = $this->rentalFor($this->customer());
        $payload = $this->notification($rental, 'settlement');
        unset($payload['signature_key']);

        $this->postJson('/midtrans/notification', $payload)->assertForbidden();
    }

    public function test_expire_notification_expires_the_rental(): void
    {
        $rental = $this->rentalFor($this->customer());

        $this->postJson('/midtrans/notification', $this->notification($rental, 'expire', ['status_code' => '407']))->assertOk();

        $this->assertSame('expire', $rental->payments()->first()->payment_status);
        $this->assertSame('expired', $rental->fresh()->status);
    }

    #[DataProvider('failureStatuses')]
    public function test_cancel_and_deny_notifications_cancel_the_rental(string $status, string $reason): void
    {
        $rental = $this->rentalFor($this->customer());

        $this->postJson('/midtrans/notification', $this->notification($rental, $status, ['status_code' => '202']))->assertOk();

        $this->assertSame($status, $rental->payments()->first()->payment_status);

        $rental->refresh();
        $this->assertSame('cancelled', $rental->status);
        $this->assertSame($reason, $rental->cancelled_reason);
    }

    public static function failureStatuses(): array
    {
        return [
            'cancel' => ['cancel', 'Pembayaran DP dibatalkan.'],
            'deny' => ['deny', 'Pembayaran DP ditolak.'],
        ];
    }

    public function test_pending_notification_stores_channel_but_keeps_status(): void
    {
        $rental = $this->rentalFor($this->customer());

        $this->postJson('/midtrans/notification', $this->notification($rental, 'pending', ['status_code' => '201', 'payment_type' => 'bank_transfer']))->assertOk();

        $payment = $rental->payments()->first();
        $this->assertSame('pending', $payment->payment_status);
        $this->assertSame('bank_transfer', $payment->payment_type);
        $this->assertSame('pending_payment', $rental->fresh()->status);
    }

    public function test_unknown_order_is_acknowledged_without_changes(): void
    {
        $rental = $this->rentalFor($this->customer());

        $this->postJson('/midtrans/notification', $this->notification($rental, 'settlement', ['order_id' => 'BK-TIDAK-ADA-DP']))->assertOk();

        $this->assertSame('pending', $rental->payments()->first()->payment_status);
    }

    public function test_amount_mismatch_is_ignored(): void
    {
        $rental = $this->rentalFor($this->customer());

        $this->postJson('/midtrans/notification', $this->notification($rental, 'settlement', ['gross_amount' => '1000.00']))->assertOk();

        $this->assertSame('pending', $rental->payments()->first()->payment_status);
        $this->assertSame('pending_payment', $rental->fresh()->status);
    }

    public function test_settlement_after_expiry_is_recorded_but_rental_is_not_revived(): void
    {
        $rental = $this->rentalFor($this->customer());

        $this->postJson('/midtrans/notification', $this->notification($rental, 'expire', ['status_code' => '407']))->assertOk();
        $this->postJson('/midtrans/notification', $this->notification($rental, 'settlement'))->assertOk();

        $this->assertSame('settlement', $rental->payments()->first()->payment_status);

        $rental->refresh();
        $this->assertSame('expired', $rental->status);
        $this->assertStringContainsString('Pembayaran DP diterima', $rental->notes);
    }

    // ---------------------------------------------------------------- Sinkronisasi (tanpa webhook)

    public function test_status_endpoint_pulls_settlement_from_midtrans(): void
    {
        $owner = $this->customer();
        $rental = $this->rentalFor($owner);
        $payment = $rental->payments()->firstOrFail();
        $payment->update(['snap_token' => 'tok-123']);

        Http::fake(['api.sandbox.midtrans.com/*' => Http::response($this->notification($rental, 'settlement'), 200)]);

        $this->actingAs($owner)->getJson("/riwayat/{$rental->id}/status")
            ->assertOk()
            ->assertJson(['status' => 'pending_verification', 'payment_status' => 'dp_paid']);

        Http::assertSent(fn (HttpRequest $request) => str_contains($request->url(), '/v2/' . $payment->order_id . '/status'));
    }

    public function test_status_endpoint_keeps_status_when_transaction_not_found(): void
    {
        $owner = $this->customer();
        $rental = $this->rentalFor($owner);
        $rental->payments()->firstOrFail()->update(['snap_token' => 'tok-123']);

        Http::fake(['api.sandbox.midtrans.com/*' => Http::response(['status_code' => '404', 'status_message' => "Transaction doesn't exist."], 200)]);

        $this->actingAs($owner)->getJson("/riwayat/{$rental->id}/status")
            ->assertOk()
            ->assertJson(['status' => 'pending_payment', 'payment_status' => 'unpaid']);
    }

    public function test_status_endpoint_does_not_call_midtrans_before_a_token_exists(): void
    {
        Http::fake();

        $owner = $this->customer();
        $rental = $this->rentalFor($owner);

        $this->actingAs($owner)->getJson("/riwayat/{$rental->id}/status")->assertOk();

        Http::assertNothingSent();
    }

    public function test_status_endpoint_is_private_to_the_owner(): void
    {
        $rental = $this->rentalFor($this->customer());

        $this->actingAs($this->customer())->getJson("/riwayat/{$rental->id}/status")->assertNotFound();
    }

    public function test_order_page_survives_midtrans_errors(): void
    {
        $owner = $this->customer();
        $rental = $this->rentalFor($owner);
        $rental->payments()->firstOrFail()->update(['snap_token' => 'tok-123']);

        Http::fake(['api.sandbox.midtrans.com/*' => Http::response('error', 500)]);

        $this->actingAs($owner)->get("/riwayat/{$rental->id}")->assertOk();
    }

    // ---------------------------------------------------------------- Tampilan

    public function test_order_page_shows_pay_button_only_while_payable(): void
    {
        $owner = $this->customer();
        $rental = $this->rentalFor($owner);

        $this->actingAs($owner)->get("/riwayat/{$rental->id}")
            ->assertOk()
            ->assertSee('id="pay-button"', false)
            ->assertSee('Bayar DP');

        $this->travel(31)->minutes();

        $this->actingAs($owner)->get("/riwayat/{$rental->id}")
            ->assertOk()
            ->assertDontSee('id="pay-button"', false)
            ->assertSee('Batas waktu pembayaran DP sudah lewat');
    }
}
