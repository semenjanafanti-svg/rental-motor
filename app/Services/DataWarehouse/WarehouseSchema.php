<?php

namespace App\Services\DataWarehouse;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Struktur tabel data warehouse (fact constellation schema).
 *
 *   Dimensi : dim_waktu, dim_motor (SCD tipe 2), dim_pelanggan, dim_staf,
 *             dim_status_rental, dim_jenis_pembayaran
 *   Fakta   : fact_penyewaan, fact_pembayaran, fact_pengembalian
 *
 * Kunci dimensi adalah surrogate key yang diberikan ETL (bukan id dari OLTP),
 * kecuali waktu_key yang memakai smart key YYYYMMDD agar mudah dibaca.
 */
class WarehouseSchema
{
    public const DIMENSIONS = ['dim_waktu', 'dim_motor', 'dim_pelanggan', 'dim_staf', 'dim_status_rental', 'dim_jenis_pembayaran'];

    public const FACTS = ['fact_penyewaan', 'fact_pembayaran', 'fact_pengembalian'];

    public static function connection(): string
    {
        return config('dw.connection', 'dw');
    }

    /** Buat database (MySQL) dan semua tabel yang belum ada. */
    public function ensure(): void
    {
        $this->ensureDatabaseExists();

        $schema = Schema::connection(self::connection());

        $this->createIfMissing($schema, 'dim_waktu', function (Blueprint $t) {
            $t->unsignedInteger('waktu_key')->primary(); // YYYYMMDD
            $t->date('tanggal')->unique();
            $t->unsignedTinyInteger('hari');            // tanggal dalam bulan (1-31)
            $t->string('nama_hari', 10);
            $t->unsignedTinyInteger('hari_ke');         // 1 = Senin ... 7 = Minggu
            $t->unsignedTinyInteger('minggu_ke');       // minggu ISO
            $t->unsignedTinyInteger('bulan');
            $t->string('nama_bulan', 10);
            $t->string('kuartal', 2);                   // Q1..Q4
            $t->unsignedSmallInteger('tahun');
            $t->boolean('is_akhir_pekan');
            $t->boolean('is_hari_libur');
            $t->string('nama_libur', 60)->nullable();
            $t->index(['tahun', 'bulan']);
        });

        $this->createIfMissing($schema, 'dim_motor', function (Blueprint $t) {
            $t->unsignedInteger('motor_key')->primary();
            $t->unsignedBigInteger('bike_id');          // natural key dari OLTP
            $t->string('nama_motor');
            $t->string('merk', 100);
            $t->string('plat_nomor', 20);
            $t->string('kategori', 20);
            $t->unsignedSmallInteger('cc')->nullable();
            $t->string('kelas_cc', 20);
            $t->unsignedSmallInteger('tahun_produksi')->nullable();
            $t->string('warna', 50)->nullable();
            $t->decimal('tarif_harian', 12, 2);
            $t->decimal('tarif_per_jam', 12, 2);
            $t->string('status_fisik', 20);
            $t->boolean('is_dihapus')->default(false);
            // SCD tipe 2: perubahan tarif/status membuat baris versi baru.
            $t->dateTime('berlaku_mulai');
            $t->dateTime('berlaku_sampai')->nullable();
            $t->boolean('is_versi_aktif')->default(true);
            $t->index(['bike_id', 'is_versi_aktif']);
        });

        $this->createIfMissing($schema, 'dim_pelanggan', function (Blueprint $t) {
            $t->unsignedInteger('pelanggan_key')->primary();
            $t->unsignedBigInteger('user_id')->unique();
            $t->string('nama');
            $t->string('no_hp', 20)->nullable();
            $t->date('tanggal_daftar');
            $t->string('segmen', 10);                   // Baru / Berulang
            $t->unsignedInteger('jumlah_sewa_berhasil');
            $t->string('status_akun', 10);              // Aktif / Nonaktif
        });

        $this->createIfMissing($schema, 'dim_staf', function (Blueprint $t) {
            $t->unsignedInteger('staf_key')->primary(); // 0 = tanpa staf
            $t->unsignedBigInteger('user_id')->nullable()->unique();
            $t->string('nama');
            $t->string('role', 20);
        });

        $this->createIfMissing($schema, 'dim_status_rental', function (Blueprint $t) {
            $t->unsignedTinyInteger('status_key')->primary();
            $t->string('status', 30)->unique();
            $t->string('label_status', 40);
            $t->string('kelompok_status', 20);          // Berhasil / Berjalan / Menunggu / Gagal
        });

        $this->createIfMissing($schema, 'dim_jenis_pembayaran', function (Blueprint $t) {
            $t->unsignedTinyInteger('jenis_key')->primary();
            $t->string('tipe', 10);
            $t->string('label_tipe', 20);
            $t->string('metode', 20);
            $t->string('label_metode', 20);
            $t->string('kanal', 30);
            $t->string('status_pembayaran', 15);
            $t->string('label_status', 20);
            $t->unique(['tipe', 'metode', 'status_pembayaran']);
        });

        $this->createIfMissing($schema, 'fact_penyewaan', function (Blueprint $t) {
            $t->unsignedBigInteger('rental_id')->primary(); // degenerate dimension
            $t->string('booking_code', 50);                 // degenerate dimension
            $t->unsignedInteger('waktu_booking_key');
            $t->unsignedInteger('waktu_mulai_key');
            $t->unsignedInteger('waktu_kembali_key');
            $t->unsignedInteger('motor_key');
            $t->unsignedInteger('pelanggan_key');
            $t->unsignedTinyInteger('status_key');
            $t->unsignedInteger('staf_serah_terima_key');
            $t->unsignedTinyInteger('jam_mulai');
            $t->unsignedTinyInteger('jumlah_sewa');
            $t->unsignedSmallInteger('durasi_hari');
            $t->unsignedInteger('total_jam');
            $t->decimal('total_harga', 14, 2);
            $t->decimal('nominal_dp', 14, 2);
            $t->decimal('sisa_pelunasan', 14, 2);
            $t->index('waktu_mulai_key');
            $t->index(['motor_key', 'status_key']);
            $t->index('pelanggan_key');
            $t->foreign('waktu_booking_key')->references('waktu_key')->on('dim_waktu');
            $t->foreign('waktu_mulai_key')->references('waktu_key')->on('dim_waktu');
            $t->foreign('waktu_kembali_key')->references('waktu_key')->on('dim_waktu');
            $t->foreign('motor_key')->references('motor_key')->on('dim_motor');
            $t->foreign('pelanggan_key')->references('pelanggan_key')->on('dim_pelanggan');
            $t->foreign('status_key')->references('status_key')->on('dim_status_rental');
            $t->foreign('staf_serah_terima_key')->references('staf_key')->on('dim_staf');
        });

        $this->createIfMissing($schema, 'fact_pembayaran', function (Blueprint $t) {
            $t->unsignedBigInteger('payment_id')->primary();
            $t->string('order_id', 100);                    // degenerate dimension
            $t->string('booking_code', 50);
            $t->unsignedInteger('waktu_bayar_key');
            $t->unsignedInteger('motor_key');
            $t->unsignedInteger('pelanggan_key');
            $t->unsignedTinyInteger('jenis_key');
            $t->unsignedInteger('staf_penerima_key');
            $t->unsignedTinyInteger('jumlah_transaksi');
            $t->decimal('nominal_bayar', 14, 2);
            $t->decimal('nominal_refund', 14, 2);
            $t->decimal('pendapatan_bersih', 14, 2);
            $t->index('waktu_bayar_key');
            $t->index('jenis_key');
            $t->foreign('waktu_bayar_key')->references('waktu_key')->on('dim_waktu');
            $t->foreign('motor_key')->references('motor_key')->on('dim_motor');
            $t->foreign('pelanggan_key')->references('pelanggan_key')->on('dim_pelanggan');
            $t->foreign('jenis_key')->references('jenis_key')->on('dim_jenis_pembayaran');
            $t->foreign('staf_penerima_key')->references('staf_key')->on('dim_staf');
        });

        $this->createIfMissing($schema, 'fact_pengembalian', function (Blueprint $t) {
            $t->unsignedBigInteger('return_id')->primary();
            $t->string('booking_code', 50);
            $t->unsignedInteger('waktu_kembali_aktual_key');
            $t->unsignedInteger('motor_key');
            $t->unsignedInteger('pelanggan_key');
            $t->unsignedInteger('staf_pemeriksa_key');
            $t->unsignedTinyInteger('jumlah_pengembalian');
            $t->unsignedInteger('jam_terlambat');
            $t->decimal('denda_telat', 14, 2);
            $t->decimal('denda_kerusakan', 14, 2);
            $t->decimal('denda_bensin', 14, 2);
            $t->decimal('total_denda', 14, 2);
            $t->boolean('is_terlambat');
            $t->boolean('is_rusak');
            $t->index('waktu_kembali_aktual_key');
            $t->foreign('waktu_kembali_aktual_key')->references('waktu_key')->on('dim_waktu');
            $t->foreign('motor_key')->references('motor_key')->on('dim_motor');
            $t->foreign('pelanggan_key')->references('pelanggan_key')->on('dim_pelanggan');
            $t->foreign('staf_pemeriksa_key')->references('staf_key')->on('dim_staf');
        });

        $this->createIfMissing($schema, 'etl_log', function (Blueprint $t) {
            $t->id();
            $t->dateTime('mulai');
            $t->dateTime('selesai')->nullable();
            $t->string('status', 10);                   // running / sukses / gagal
            $t->json('jumlah_baris')->nullable();
            $t->text('pesan')->nullable();
        });
    }

    /** Hapus seluruh tabel DW (fakta dulu karena punya foreign key). */
    public function drop(): void
    {
        $schema = Schema::connection(self::connection());

        foreach (['etl_log', ...self::FACTS, ...self::DIMENSIONS] as $table) {
            $schema->dropIfExists($table);
        }
    }

    public function isReady(): bool
    {
        try {
            return Schema::connection(self::connection())->hasTable('fact_penyewaan');
        } catch (\Throwable) {
            return false;
        }
    }

    private function createIfMissing(Builder $schema, string $table, callable $definition): void
    {
        if (! $schema->hasTable($table)) {
            $schema->create($table, $definition);
        }
    }

    /** MySQL: buat database DW-nya dulu lewat koneksi operasional (user yang sama). */
    private function ensureDatabaseExists(): void
    {
        $config = config('database.connections.'.self::connection());

        if (($config['driver'] ?? null) !== 'mysql') {
            return;
        }

        $name = str_replace('`', '', $config['database']);

        DB::connection(config('database.default'))
            ->statement("CREATE DATABASE IF NOT EXISTS `{$name}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

        DB::purge(self::connection());
    }
}
