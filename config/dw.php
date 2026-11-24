<?php

/*
| Konfigurasi data warehouse Mitra Jalan (fact constellation / galaxy schema).
| Rancangan mengikuti laporan Tugas Data Warehouse Kelompok C SD-A2:
| 3 fakta (penyewaan, pembayaran, pengembalian) dan 6 dimensi.
*/

return [
    'connection' => 'dw',

    // ETL otomatis setiap hari jam 01:00 (butuh `php artisan schedule:work` atau cron).
    'schedule_at' => '01:00',

    // Hari libur nasional untuk atribut dim_waktu.is_hari_libur.
    // Tanggal hari raya keagamaan 2026 adalah perkiraan; sesuaikan dengan SKB 3 Menteri.
    'holidays' => [
        '2025-01-01' => 'Tahun Baru Masehi',
        '2025-01-27' => 'Isra Mikraj',
        '2025-01-29' => 'Tahun Baru Imlek',
        '2025-03-29' => 'Hari Suci Nyepi',
        '2025-03-31' => 'Idulfitri',
        '2025-04-01' => 'Idulfitri',
        '2025-04-18' => 'Wafat Yesus Kristus',
        '2025-05-01' => 'Hari Buruh',
        '2025-05-12' => 'Hari Raya Waisak',
        '2025-05-29' => 'Kenaikan Yesus Kristus',
        '2025-06-01' => 'Hari Lahir Pancasila',
        '2025-06-06' => 'Iduladha',
        '2025-06-27' => 'Tahun Baru Islam',
        '2025-08-17' => 'Hari Kemerdekaan RI',
        '2025-09-05' => 'Maulid Nabi Muhammad',
        '2025-12-25' => 'Hari Raya Natal',
        '2025-12-26' => 'Cuti Bersama Natal',
        '2026-01-01' => 'Tahun Baru Masehi',
        '2026-01-16' => 'Isra Mikraj',
        '2026-02-17' => 'Tahun Baru Imlek',
        '2026-03-19' => 'Hari Suci Nyepi',
        '2026-03-20' => 'Idulfitri',
        '2026-03-21' => 'Idulfitri',
        '2026-04-03' => 'Wafat Yesus Kristus',
        '2026-05-01' => 'Hari Buruh',
        '2026-05-14' => 'Kenaikan Yesus Kristus',
        '2026-05-27' => 'Iduladha',
        '2026-05-31' => 'Hari Raya Waisak',
        '2026-06-01' => 'Hari Lahir Pancasila',
        '2026-06-16' => 'Tahun Baru Islam',
        '2026-08-17' => 'Hari Kemerdekaan RI',
        '2026-08-25' => 'Maulid Nabi Muhammad',
        '2026-12-25' => 'Hari Raya Natal',
    ],

    // dim_status_rental: status operasional -> label & kelompok status.
    'rental_statuses' => [
        'pending_payment' => ['Menunggu pembayaran', 'Menunggu'],
        'pending_verification' => ['Menunggu verifikasi', 'Menunggu'],
        'approved' => ['Terkonfirmasi', 'Berjalan'],
        'active' => ['Sedang disewa', 'Berjalan'],
        'completed' => ['Selesai', 'Berhasil'],
        'cancelled' => ['Dibatalkan', 'Gagal'],
        'expired' => ['Kedaluwarsa', 'Gagal'],
        'no_show' => ['Tidak datang', 'Gagal'],
    ],

    // dim_jenis_pembayaran (junk dimension: semua kombinasi tipe x metode x status).
    'payment_types' => [
        'dp' => 'DP',
        'balance' => 'Pelunasan',
        'fine' => 'Denda',
    ],
    'payment_methods' => [
        'cash' => ['Tunai', 'Tunai di lokasi'],
        'manual_transfer' => ['Transfer manual', 'QRIS statis'],
    ],
    'payment_statuses' => [
        'pending' => 'Menunggu',
        'settlement' => 'Lunas',
        'expire' => 'Kedaluwarsa',
        'cancel' => 'Dibatalkan',
        'refund' => 'Direfund',
    ],

    // Pelanggan dengan >= nilai ini sewa berhasil masuk segmen "Berulang".
    'repeat_customer_min_rentals' => 2,
];
