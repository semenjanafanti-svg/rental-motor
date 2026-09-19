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
            $table->string('transaction_id')->nullable(); // dari Midtrans
            $table->enum('type', ['dp', 'balance', 'fine']);
            $table->enum('method', ['midtrans', 'cash', 'manual_transfer']);
            $table->string('payment_type', 50)->nullable(); // kanal Midtrans: qris, bank_transfer, gopay, dst.
            $table->decimal('gross_amount', 12, 2);
            $table->enum('payment_status', ['pending', 'settlement', 'expire', 'cancel', 'deny', 'refund'])->default('pending');
            $table->string('snap_token')->nullable();
            $table->dateTime('paid_at')->nullable();
            $table->decimal('refunded_amount', 12, 2)->default(0);
            $table->foreignId('received_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->json('raw_response')->nullable(); // payload notifikasi Midtrans untuk audit
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
