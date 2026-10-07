<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bikes', function (Blueprint $table): void {
            $columns = array_values(array_filter(
                ['hourly_rate', 'facilities'],
                fn (string $column): bool => Schema::hasColumn('bikes', $column),
            ));

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });

        if (Schema::hasColumn('payments', 'payment_type')) {
            Schema::table('payments', function (Blueprint $table): void {
                $table->dropColumn('payment_type');
            });
        }

        if (DB::getDriverName() === 'mysql'
            && DB::table('payments')->where('payment_status', 'deny')->doesntExist()) {
            DB::statement("ALTER TABLE payments MODIFY payment_status ENUM('pending','settlement','expire','cancel','refund') NOT NULL DEFAULT 'pending'");
        }

        if (Schema::hasColumn('rentals', 'resubmission_expires_at')) {
            Schema::table('rentals', function (Blueprint $table): void {
                $table->dropIndex(['resubmission_expires_at']);
                $table->dropColumn('resubmission_expires_at');
            });
        }

        if (config('cache.default') === 'file') {
            Schema::dropIfExists('cache_locks');
            Schema::dropIfExists('cache');
        }
    }

    public function down(): void
    {
        Schema::table('bikes', function (Blueprint $table): void {
            if (! Schema::hasColumn('bikes', 'hourly_rate')) {
                $table->decimal('hourly_rate', 12, 2)->nullable();
            }

            if (! Schema::hasColumn('bikes', 'facilities')) {
                $table->json('facilities')->nullable();
            }
        });

        if (! Schema::hasColumn('payments', 'payment_type')) {
            Schema::table('payments', function (Blueprint $table): void {
                $table->string('payment_type', 50)->nullable();
            });
        }

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE payments MODIFY payment_status ENUM('pending','settlement','expire','cancel','deny','refund') NOT NULL DEFAULT 'pending'");
        }

        if (! Schema::hasColumn('rentals', 'resubmission_expires_at')) {
            Schema::table('rentals', function (Blueprint $table): void {
                $table->dateTime('resubmission_expires_at')->nullable()->index();
            });
        }

        if (config('cache.default') === 'file') {
            Schema::create('cache', function (Blueprint $table): void {
                $table->string('key')->primary();
                $table->mediumText('value');
                $table->bigInteger('expiration')->index();
            });

            Schema::create('cache_locks', function (Blueprint $table): void {
                $table->string('key')->primary();
                $table->string('owner');
                $table->bigInteger('expiration')->index();
            });
        }
    }
};
