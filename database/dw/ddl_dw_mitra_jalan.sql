-- =========================================================
-- DDL Data Warehouse Mitra Jalan (database: dw_mitra_jalan)
-- Fact constellation schema: 6 dimensi + 3 fakta.
-- Dibuat otomatis oleh `php artisan dw:etl` (App\Services\DataWarehouse\WarehouseSchema).
-- File ini untuk dokumentasi / import manual (mis. di Pentaho atau phpMyAdmin).
-- =========================================================

CREATE DATABASE IF NOT EXISTS `dw_mitra_jalan` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `dw_mitra_jalan`;

-- dim_waktu
CREATE TABLE `dim_waktu` (
  `waktu_key` int unsigned NOT NULL,
  `tanggal` date NOT NULL,
  `hari` tinyint unsigned NOT NULL,
  `nama_hari` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  `hari_ke` tinyint unsigned NOT NULL,
  `minggu_ke` tinyint unsigned NOT NULL,
  `bulan` tinyint unsigned NOT NULL,
  `nama_bulan` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  `kuartal` varchar(2) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tahun` smallint unsigned NOT NULL,
  `is_akhir_pekan` tinyint(1) NOT NULL,
  `is_hari_libur` tinyint(1) NOT NULL,
  `nama_libur` varchar(60) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`waktu_key`),
  UNIQUE KEY `dim_waktu_tanggal_unique` (`tanggal`),
  KEY `dim_waktu_tahun_bulan_index` (`tahun`,`bulan`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- dim_motor
CREATE TABLE `dim_motor` (
  `motor_key` int unsigned NOT NULL,
  `bike_id` bigint unsigned NOT NULL,
  `nama_motor` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `merk` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `plat_nomor` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `kategori` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `cc` smallint unsigned DEFAULT NULL,
  `kelas_cc` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tahun_produksi` smallint unsigned DEFAULT NULL,
  `warna` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tarif_harian` decimal(12,2) NOT NULL,
  `tarif_per_jam` decimal(12,2) NOT NULL,
  `status_fisik` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_dihapus` tinyint(1) NOT NULL DEFAULT '0',
  `berlaku_mulai` datetime NOT NULL,
  `berlaku_sampai` datetime DEFAULT NULL,
  `is_versi_aktif` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`motor_key`),
  KEY `dim_motor_bike_id_is_versi_aktif_index` (`bike_id`,`is_versi_aktif`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- dim_pelanggan
CREATE TABLE `dim_pelanggan` (
  `pelanggan_key` int unsigned NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `nama` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `no_hp` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tanggal_daftar` date NOT NULL,
  `segmen` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  `jumlah_sewa_berhasil` int unsigned NOT NULL,
  `status_akun` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`pelanggan_key`),
  UNIQUE KEY `dim_pelanggan_user_id_unique` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- dim_staf
CREATE TABLE `dim_staf` (
  `staf_key` int unsigned NOT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `nama` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `role` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`staf_key`),
  UNIQUE KEY `dim_staf_user_id_unique` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- dim_status_rental
CREATE TABLE `dim_status_rental` (
  `status_key` tinyint unsigned NOT NULL,
  `status` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `label_status` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `kelompok_status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`status_key`),
  UNIQUE KEY `dim_status_rental_status_unique` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- dim_jenis_pembayaran
CREATE TABLE `dim_jenis_pembayaran` (
  `jenis_key` tinyint unsigned NOT NULL,
  `tipe` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  `label_tipe` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `metode` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `label_metode` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `kanal` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status_pembayaran` varchar(15) COLLATE utf8mb4_unicode_ci NOT NULL,
  `label_status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`jenis_key`),
  UNIQUE KEY `dim_jenis_pembayaran_tipe_metode_status_pembayaran_unique` (`tipe`,`metode`,`status_pembayaran`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- fact_penyewaan
CREATE TABLE `fact_penyewaan` (
  `rental_id` bigint unsigned NOT NULL,
  `booking_code` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `waktu_booking_key` int unsigned NOT NULL,
  `waktu_mulai_key` int unsigned NOT NULL,
  `waktu_kembali_key` int unsigned NOT NULL,
  `motor_key` int unsigned NOT NULL,
  `pelanggan_key` int unsigned NOT NULL,
  `status_key` tinyint unsigned NOT NULL,
  `staf_serah_terima_key` int unsigned NOT NULL,
  `jam_mulai` tinyint unsigned NOT NULL,
  `jumlah_sewa` tinyint unsigned NOT NULL,
  `durasi_hari` smallint unsigned NOT NULL,
  `total_jam` int unsigned NOT NULL,
  `total_harga` decimal(14,2) NOT NULL,
  `nominal_dp` decimal(14,2) NOT NULL,
  `sisa_pelunasan` decimal(14,2) NOT NULL,
  PRIMARY KEY (`rental_id`),
  KEY `fact_penyewaan_waktu_mulai_key_index` (`waktu_mulai_key`),
  KEY `fact_penyewaan_motor_key_status_key_index` (`motor_key`,`status_key`),
  KEY `fact_penyewaan_pelanggan_key_index` (`pelanggan_key`),
  KEY `fact_penyewaan_waktu_booking_key_foreign` (`waktu_booking_key`),
  KEY `fact_penyewaan_waktu_kembali_key_foreign` (`waktu_kembali_key`),
  KEY `fact_penyewaan_status_key_foreign` (`status_key`),
  KEY `fact_penyewaan_staf_serah_terima_key_foreign` (`staf_serah_terima_key`),
  CONSTRAINT `fact_penyewaan_motor_key_foreign` FOREIGN KEY (`motor_key`) REFERENCES `dim_motor` (`motor_key`),
  CONSTRAINT `fact_penyewaan_pelanggan_key_foreign` FOREIGN KEY (`pelanggan_key`) REFERENCES `dim_pelanggan` (`pelanggan_key`),
  CONSTRAINT `fact_penyewaan_staf_serah_terima_key_foreign` FOREIGN KEY (`staf_serah_terima_key`) REFERENCES `dim_staf` (`staf_key`),
  CONSTRAINT `fact_penyewaan_status_key_foreign` FOREIGN KEY (`status_key`) REFERENCES `dim_status_rental` (`status_key`),
  CONSTRAINT `fact_penyewaan_waktu_booking_key_foreign` FOREIGN KEY (`waktu_booking_key`) REFERENCES `dim_waktu` (`waktu_key`),
  CONSTRAINT `fact_penyewaan_waktu_kembali_key_foreign` FOREIGN KEY (`waktu_kembali_key`) REFERENCES `dim_waktu` (`waktu_key`),
  CONSTRAINT `fact_penyewaan_waktu_mulai_key_foreign` FOREIGN KEY (`waktu_mulai_key`) REFERENCES `dim_waktu` (`waktu_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- fact_pembayaran
CREATE TABLE `fact_pembayaran` (
  `payment_id` bigint unsigned NOT NULL,
  `order_id` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `booking_code` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `waktu_bayar_key` int unsigned NOT NULL,
  `motor_key` int unsigned NOT NULL,
  `pelanggan_key` int unsigned NOT NULL,
  `jenis_key` tinyint unsigned NOT NULL,
  `staf_penerima_key` int unsigned NOT NULL,
  `jumlah_transaksi` tinyint unsigned NOT NULL,
  `nominal_bayar` decimal(14,2) NOT NULL,
  `nominal_refund` decimal(14,2) NOT NULL,
  `pendapatan_bersih` decimal(14,2) NOT NULL,
  PRIMARY KEY (`payment_id`),
  KEY `fact_pembayaran_waktu_bayar_key_index` (`waktu_bayar_key`),
  KEY `fact_pembayaran_jenis_key_index` (`jenis_key`),
  KEY `fact_pembayaran_motor_key_foreign` (`motor_key`),
  KEY `fact_pembayaran_pelanggan_key_foreign` (`pelanggan_key`),
  KEY `fact_pembayaran_staf_penerima_key_foreign` (`staf_penerima_key`),
  CONSTRAINT `fact_pembayaran_jenis_key_foreign` FOREIGN KEY (`jenis_key`) REFERENCES `dim_jenis_pembayaran` (`jenis_key`),
  CONSTRAINT `fact_pembayaran_motor_key_foreign` FOREIGN KEY (`motor_key`) REFERENCES `dim_motor` (`motor_key`),
  CONSTRAINT `fact_pembayaran_pelanggan_key_foreign` FOREIGN KEY (`pelanggan_key`) REFERENCES `dim_pelanggan` (`pelanggan_key`),
  CONSTRAINT `fact_pembayaran_staf_penerima_key_foreign` FOREIGN KEY (`staf_penerima_key`) REFERENCES `dim_staf` (`staf_key`),
  CONSTRAINT `fact_pembayaran_waktu_bayar_key_foreign` FOREIGN KEY (`waktu_bayar_key`) REFERENCES `dim_waktu` (`waktu_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- fact_pengembalian
CREATE TABLE `fact_pengembalian` (
  `return_id` bigint unsigned NOT NULL,
  `booking_code` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `waktu_kembali_aktual_key` int unsigned NOT NULL,
  `motor_key` int unsigned NOT NULL,
  `pelanggan_key` int unsigned NOT NULL,
  `staf_pemeriksa_key` int unsigned NOT NULL,
  `jumlah_pengembalian` tinyint unsigned NOT NULL,
  `jam_terlambat` int unsigned NOT NULL,
  `denda_telat` decimal(14,2) NOT NULL,
  `denda_kerusakan` decimal(14,2) NOT NULL,
  `denda_bensin` decimal(14,2) NOT NULL,
  `total_denda` decimal(14,2) NOT NULL,
  `is_terlambat` tinyint(1) NOT NULL,
  `is_rusak` tinyint(1) NOT NULL,
  PRIMARY KEY (`return_id`),
  KEY `fact_pengembalian_waktu_kembali_aktual_key_index` (`waktu_kembali_aktual_key`),
  KEY `fact_pengembalian_motor_key_foreign` (`motor_key`),
  KEY `fact_pengembalian_pelanggan_key_foreign` (`pelanggan_key`),
  KEY `fact_pengembalian_staf_pemeriksa_key_foreign` (`staf_pemeriksa_key`),
  CONSTRAINT `fact_pengembalian_motor_key_foreign` FOREIGN KEY (`motor_key`) REFERENCES `dim_motor` (`motor_key`),
  CONSTRAINT `fact_pengembalian_pelanggan_key_foreign` FOREIGN KEY (`pelanggan_key`) REFERENCES `dim_pelanggan` (`pelanggan_key`),
  CONSTRAINT `fact_pengembalian_staf_pemeriksa_key_foreign` FOREIGN KEY (`staf_pemeriksa_key`) REFERENCES `dim_staf` (`staf_key`),
  CONSTRAINT `fact_pengembalian_waktu_kembali_aktual_key_foreign` FOREIGN KEY (`waktu_kembali_aktual_key`) REFERENCES `dim_waktu` (`waktu_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- etl_log
CREATE TABLE `etl_log` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `mulai` datetime NOT NULL,
  `selesai` datetime DEFAULT NULL,
  `status` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  `jumlah_baris` json DEFAULT NULL,
  `pesan` text COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

