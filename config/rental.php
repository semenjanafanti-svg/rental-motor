<?php

return [
    // Sewa dihitung per 24 jam. Penyewa memilih tanggal mulai, jam mulai, dan tanggal
    // pengembalian; jam pengembalian otomatis sama dengan jam mulai (kelipatan 24 jam).
    'min_days' => 1,   // minimal 1 hari = 24 jam
    'max_days' => 30,  // batas atas durasi sewa (asumsi, ubah sesuai kebijakan)

    // Batas waktu bayar DP setelah checkout (menit): transfer QRIS + unggah bukti.
    // Slot motor terkunci selama waktu ini; lewat dari itu pesanan expired.
    'lock_minutes' => 10,

    // QRIS toko (gambar di public/images/qris.png).
    'qris' => [
        'image' => 'images/qris.png',
    ],

    // Persentase DP dari total harga, dan kelipatan pembulatan ke atas (rupiah).
    'dp_percent' => 30,
    'dp_rounding' => 1000,

    // Toleransi no-show setelah start_time (menit).
    'no_show_tolerance_minutes' => 180,

    // Toleransi keterlambatan sebelum denda dihitung (menit).
    // Denda = jam keterlambatan (dibulatkan ke atas) x tarif per jam yang di-snapshot.
    'late_tolerance_minutes' => 30,

    // Aturan refund saat customer membatalkan.
    // Jam mulai sewa (jam bulat). Jam kembali otomatis sama dengan jam mulai.
    'open_hour' => 6,
    'close_hour' => 21,

    // Customer hanya boleh membatalkan H-3 atau lebih awal, refund DP 100%.
    'cancellation' => [
        'min_days_before' => 3,
    ],
];
