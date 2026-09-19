<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rental_reminders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rental_id')->constrained('rentals')->restrictOnDelete();
            $table->enum('type', ['pickup_confirmation', 'return_2h', 'return_30m', 'overdue']);
            $table->dateTime('scheduled_at');
            $table->enum('status', ['pending', 'sent', 'skipped'])->default('pending');
            $table->foreignId('sent_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->dateTime('sent_at')->nullable();
            $table->text('message_snapshot')->nullable();
            $table->timestamps();

            $table->index(['status', 'scheduled_at']); // halaman "Reminder Hari Ini"
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rental_reminders');
    }
};
