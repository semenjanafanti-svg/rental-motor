<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->string('proof_photo')->nullable()->after('snap_token');      // path di disk privat
            $table->dateTime('proof_uploaded_at')->nullable()->after('proof_photo');
            $table->string('rejection_reason')->nullable()->after('proof_uploaded_at');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn(['proof_photo', 'proof_uploaded_at', 'rejection_reason']);
        });
    }
};
