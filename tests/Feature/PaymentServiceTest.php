<?php

namespace Tests\Feature;

use App\Models\Bike;
use App\Models\User;
use App\Services\BookingService;
use App\Services\PaymentService;
use App\Services\RentalExpirationService;
use App\Support\VerificationMessage;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PaymentServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $this->travelTo(Carbon::parse('2026-09-23 09:00:00'));
    }

    private function booking(): array
    {
        $customer = User::factory()->create(['phone_number' => '628123456789']);
        $bike = Bike::create([
            'name' => 'Vario 160',
            'brand' => 'Honda',
            'license_plate' => 'L 1234 AB',
            'category' => 'matic',
            'cc' => 160,
            'year' => 2024,
            'daily_rate' => 100000,
            'hourly_rate' => 12000,
            'status' => 'available',
        ]);

        $rental = app(BookingService::class)->create($customer, [
            'bike_id' => $bike->id,
            'start_date' => '2026-09-24',
            'start_time' => '09:00',
            'end_date' => '2026-09-25',
            'ktp_photo' => 'verifications/ktp/original.jpg',
            'sim_photo' => 'verifications/sim/original.jpg',
        ]);

        return [$rental, $customer];
    }

    private function submitProof($rental): void
    {
        app(PaymentService::class)->submitDpProof($rental, UploadedFile::fake()->image('proof.jpg'));
    }

    public function test_rejected_payment_proof_gets_three_hours_and_a_whatsapp_message(): void
    {
        [$rental] = $this->booking();
        $this->submitProof($rental);

        app(PaymentService::class)->rejectDpProof($rental, 'Nominal pada bukti tidak sesuai.');
        $rental->refresh();

        $this->assertSame('pending_payment', $rental->status);
        $this->assertTrue($rental->expires_at->equalTo(now()->addHours(3)));
        $this->assertTrue(VerificationMessage::hasResubmissionRequest($rental));
        $this->assertStringContainsString('wa.me/628123456789', VerificationMessage::waLink($rental));
        $this->assertStringContainsString(rawurlencode('3 jam'), VerificationMessage::waLink($rental));
    }

    public function test_rejected_documents_get_three_hours_and_expire_when_the_deadline_passes(): void
    {
        [$rental] = $this->booking();
        $this->submitProof($rental);
        $admin = User::factory()->create(['role' => 'admin']);

        app(PaymentService::class)->rejectDocuments($rental, $admin, 'Foto KTP tidak terbaca.');
        $rental->refresh();

        $this->assertSame('pending_verification', $rental->status);
        $this->assertTrue($rental->resubmission_expires_at->equalTo(now()->addHours(3)));
        $this->assertTrue(VerificationMessage::hasResubmissionRequest($rental));

        $this->travel(181)->minutes();
        $this->assertSame(1, app(RentalExpirationService::class)->expireOverdue());

        $this->assertSame('expired', $rental->fresh()->status);
    }
}
