<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rental_returns', function (Blueprint $table) {
            // Denda bila bensin tidak penuh saat dikembalikan. Diinput manual oleh admin.
            $table->decimal('fuel_fee', 12, 2)->default(0)->after('damage_fee');
        });
    }

    public function down(): void
    {
        Schema::table('rental_returns', function (Blueprint $table) {
            $table->dropColumn('fuel_fee');
        });
    }
};
