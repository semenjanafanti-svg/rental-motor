<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('rentals', 'verification_rejection_count')) {
            Schema::table('rentals', function (Blueprint $table): void {
                $table->dropColumn('verification_rejection_count');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('rentals', 'verification_rejection_count')) {
            Schema::table('rentals', function (Blueprint $table): void {
                $table->unsignedTinyInteger('verification_rejection_count')->default(0);
            });
        }
    }
};
