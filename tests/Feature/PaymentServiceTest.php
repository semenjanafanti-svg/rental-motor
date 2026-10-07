<?php

namespace Tests\Feature;

use App\Models\Bike;
use App\Models\User;
use App\Services\BookingService;
use App\Services\PaymentService;
use App\Services\WhatsAppTemplateService;
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

    public function test_rejected_payment_proof_immediately_cancels_and_refunds_with_a_whatsapp_template(): void
    {
        [$rental] = $this->booking();
        $this->submitProof($rental);
        $admin = User::factory()->create(['role' => 'admin']);

        app(PaymentService::class)->rejectDpProof($rental, $admin, 'Nominal pada bukti tidak sesuai.');
        $rental->refresh();
        $payment = $rental->payments()->where('type', 'dp')->firstOrFail();

        $this->assertSame('cancelled', $rental->status);
        $this->assertSame('refunded', $rental->payment_status);
        $this->assertSame('refund', $payment->payment_status);
        $this->assertSame((string) $payment->gross_amount, (string) $payment->refunded_amount);
        $this->assertSame('Nominal pada bukti tidak sesuai.', $payment->rejection_reason);
        $this->assertArrayNotHasKey('resubmission_expires_at', $rental->getAttributes());

        $link = app(WhatsAppTemplateService::class)->rejectedProofLink($rental);
        $this->assertStringContainsString('wa.me/628123456789', $link);
        $this->assertStringContainsString(rawurlencode('bukti pembayaran DP ditolak'), $link);
        $this->assertStringContainsString(rawurlencode('Nominal pada bukti tidak sesuai.'), $link);
        $this->assertStringNotContainsString(rawurlencode(url('/motor')), $link);
    }

    public function test_rejected_documents_immediately_cancels_and_refunds_with_a_whatsapp_template(): void
    {
        [$rental] = $this->booking();
        $this->submitProof($rental);
        $admin = User::factory()->create();
        $admin->forceFill(['role' => 'admin'])->save();

        app(PaymentService::class)->rejectDocuments($rental, $admin, 'Foto KTP tidak terbaca.');
        $rental->refresh();
        $payment = $rental->payments()->where('type', 'dp')->firstOrFail();

        $this->assertSame('cancelled', $rental->status);
        $this->assertSame('refunded', $rental->payment_status);
        $this->assertSame('rejected', $rental->verification_status);
        $this->assertSame('Foto KTP tidak terbaca.', $rental->verification_rejection_reason);
        $this->assertSame('refund', $payment->payment_status);
        $this->assertSame((string) $payment->gross_amount, (string) $payment->refunded_amount);
        $this->assertArrayNotHasKey('resubmission_expires_at', $rental->getAttributes());

        $link = app(WhatsAppTemplateService::class)->rejectedDocumentLink($rental);
        $this->assertStringContainsString('wa.me/628123456789', $link);
        $this->assertStringContainsString(rawurlencode('dokumen (KTP/SIM) ditolak'), $link);
        $this->assertStringContainsString(rawurlencode('Foto KTP tidak terbaca.'), $link);
        $this->assertStringNotContainsString(rawurlencode(url('/motor')), $link);
    }
}
