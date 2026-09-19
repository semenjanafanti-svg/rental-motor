<?php

return [
    // Durasi sewa minimal (jam). Divalidasi di frontend dan server.
    'min_hours' => 12,

    // Masa kunci slot sementara setelah checkout (menit).
    // Masa berlaku Snap Midtrans harus disamakan dengan nilai ini.
    'lock_minutes' => 30,

    // Persentase DP dari total harga, dan kelipatan pembulatan ke atas (rupiah).
    'dp_percent' => 30,
    'dp_rounding' => 1000,

    // Toleransi no-show setelah start_time (menit).
    'no_show_tolerance_minutes' => 120,

    // Toleransi keterlambatan sebelum denda dihitung (menit).
    'late_tolerance_minutes' => 30,

    // Aturan refund saat customer membatalkan.
    'cancellation' => [
        'full_refund_days_before' => 2,     // H-2 atau lebih awal: refund penuh
        'partial_refund_days_before' => 1,  // H-1: refund sebagian
        'partial_refund_percent' => 50,
        // Hari H: DP hangus
    ],
];
