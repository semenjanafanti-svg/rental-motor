<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bikes', function (Blueprint $table) {
            // Katalog: status selalu dipakai, lalu kategori/harga atau nama sebagai urutan.
            $table->index(['status', 'category', 'daily_rate'], 'bikes_catalog_filter_index');
            $table->index(['status', 'name'], 'bikes_catalog_name_index');
        });

        Schema::table('rentals', function (Blueprint $table) {
            // Riwayat customer dipaginasi menurut waktu pembuatan.
            $table->index(['user_id', 'created_at'], 'rentals_user_created_index');
        });
    }

    public function down(): void
    {
        Schema::table('rentals', function (Blueprint $table) {
            $table->dropIndex('rentals_user_created_index');
        });

        Schema::table('bikes', function (Blueprint $table) {
            $table->dropIndex('bikes_catalog_filter_index');
            $table->dropIndex('bikes_catalog_name_index');
        });
    }
};
