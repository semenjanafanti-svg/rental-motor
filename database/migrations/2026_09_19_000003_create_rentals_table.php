<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rentals', function (Blueprint $table) {
            $table->id();
            $table->string('booking_code', 50)->unique(); // contoh: BK-20260919-0001
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('bike_id')->constrained('bikes')->restrictOnDelete();

            $table->dateTime('start_time');
            $table->dateTime('end_time');
            $table->unsignedInteger('total_hours');

            // Snapshot tarif saat booking
            $table->decimal('hourly_rate_applied', 12, 2);
            $table->decimal('daily_rate_applied', 12, 2);
            $table->decimal('total_price', 12, 2);
            $table->decimal('dp_amount', 12, 2);
            $table->decimal('balance_amount', 12, 2);

            $table->enum('payment_status', ['unpaid', 'dp_paid', 'fully_paid', 'refunded'])->default('unpaid');
            $table->enum('status', [
                'pending_payment',
                'pending_verification',
                'approved',
                'active',
                'completed',
                'cancelled',
                'expired',
                'no_show',
            ])->default('pending_payment');

            $table->dateTime('expires_at')->nullable();
            $table->dateTime('picked_up_at')->nullable();
            $table->foreignId('handed_over_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('cancelled_reason')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['bike_id', 'start_time', 'end_time', 'status']); // cek overlap
            $table->index(['status', 'end_time']);                          // rental mendekati/lewat batas kembali
            $table->index(['status', 'expires_at']);                        // job pembatalan otomatis
        });

        // Perlindungan level database (MySQL 8.0.16+)
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE rentals ADD CONSTRAINT chk_rentals_time CHECK (end_time > start_time)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('rentals');
    }
};
