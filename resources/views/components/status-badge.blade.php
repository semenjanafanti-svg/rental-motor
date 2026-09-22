@props(['status', 'type' => 'rental'])

@php
    // Nama kelas ditulis utuh di sini agar terdeteksi Tailwind. Varian: amber, teal, rust, neutral.
    $maps = [
        // rentals.status
        'rental' => [
            'pending_payment' => ['Menunggu pembayaran DP', 'badge-amber'],
            'pending_verification' => ['Menunggu verifikasi admin', 'badge-amber'],
            'approved' => ['Disetujui, menunggu pengambilan', 'badge-teal'],
            'active' => ['Sedang disewa', 'badge-teal'],
            'completed' => ['Selesai', 'badge-neutral'],
            'cancelled' => ['Dibatalkan', 'badge-rust'],
            'expired' => ['Kedaluwarsa', 'badge-neutral'],
            'no_show' => ['Tidak hadir', 'badge-rust'],
        ],
        // rentals.payment_status
        'payment' => [
            'unpaid' => ['Belum dibayar', 'badge-amber'],
            'dp_paid' => ['DP terbayar', 'badge-teal'],
            'fully_paid' => ['Lunas', 'badge-teal'],
            'refunded' => ['Dikembalikan', 'badge-neutral'],
        ],
        // payments.payment_status
        'transaction' => [
            'pending' => ['Menunggu', 'badge-amber'],
            'settlement' => ['Berhasil', 'badge-teal'],
            'expire' => ['Kedaluwarsa', 'badge-neutral'],
            'cancel' => ['Dibatalkan', 'badge-rust'],
            'deny' => ['Ditolak', 'badge-rust'],
            'refund' => ['Refund', 'badge-neutral'],
        ],
        // verifications.status
        'verification' => [
            'pending' => ['Menunggu verifikasi', 'badge-amber'],
            'approved' => ['Disetujui', 'badge-teal'],
            'rejected' => ['Ditolak', 'badge-rust'],
        ],
    ];

    [$label, $class] = $maps[$type][$status] ?? [$status, 'badge-neutral'];
@endphp

<span {{ $attributes->merge(['class' => 'badge badge-dot ' . $class]) }}>{{ $label }}</span>
