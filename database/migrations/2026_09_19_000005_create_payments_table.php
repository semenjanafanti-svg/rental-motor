<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rental_id')->constrained('rentals')->restrictOnDelete(); // otomatis ter-index
            $table->string('order_id', 100)->unique(); // contoh: BK-20260919-0001-DP
            $table->enum('type', ['dp', 'balance', 'fine']);
            $table->enum('method', ['cash', 'manual_transfer']);
            $table->decimal('gross_amount', 12, 2);
            $table->enum('payment_status', ['pending', 'settlement', 'expire', 'cancel', 'refund'])->default('pending');
            $table->dateTime('paid_at')->nullable();
            $table->decimal('refunded_amount', 12, 2)->default(0);
            $table->foreignId('received_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('proof_photo')->nullable(); // path di disk privat
            $table->dateTime('proof_uploaded_at')->nullable();
            $table->string('rejection_reason')->nullable();
            $table->unsignedTinyInteger('rejection_count')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
