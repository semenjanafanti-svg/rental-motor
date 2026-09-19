<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bikes', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('brand', 100);
            $table->string('license_plate', 20)->unique();
            $table->enum('category', ['matic', 'manual', 'sport']);
            $table->unsignedSmallInteger('cc')->nullable();
            $table->unsignedSmallInteger('year')->nullable();
            $table->decimal('daily_rate', 12, 2);
            $table->decimal('hourly_rate', 12, 2);
            // Hanya kondisi fisik. Ketersediaan per tanggal dihitung dari tabel rentals.
            $table->enum('status', ['available', 'maintenance', 'inactive'])->default('available');
            $table->string('photo')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bikes');
    }
};
