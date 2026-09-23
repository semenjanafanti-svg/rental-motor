<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bikes', function (Blueprint $table) {
            $table->string('color', 50)->nullable()->after('year');
            $table->json('facilities')->nullable()->after('photo');
        });

        Schema::table('payments', fn (Blueprint $table) => $table->unsignedTinyInteger('rejection_count')->default(0)->after('rejection_reason'));
        Schema::table('verifications', fn (Blueprint $table) => $table->unsignedTinyInteger('rejection_count')->default(0)->after('rejection_reason'));
    }

    public function down(): void
    {
        Schema::table('verifications', fn (Blueprint $table) => $table->dropColumn('rejection_count'));
        Schema::table('payments', fn (Blueprint $table) => $table->dropColumn('rejection_count'));
        Schema::table('bikes', fn (Blueprint $table) => $table->dropColumn(['color', 'facilities']));
    }
};
