@props(['status', 'type' => 'rental'])

@php
    // Kelas Tailwind sengaja ditulis di file Blade ini agar pasti terdeteksi Tailwind.
    $maps = [
        // rentals.status
        'rental' => [
            'pending_payment' => ['Menunggu pembayaran DP', 'bg-yellow-100 text-yellow-800'],
            'pending_verification' => ['Menunggu verifikasi dokumen', 'bg-blue-100 text-blue-800'],
            'approved' => ['Disetujui, menunggu pengambilan', 'bg-indigo-100 text-indigo-800'],
            'active' => ['Sedang disewa', 'bg-green-100 text-green-800'],
            'completed' => ['Selesai', 'bg-gray-100 text-gray-700'],
            'cancelled' => ['Dibatalkan', 'bg-red-100 text-red-800'],
            'expired' => ['Kedaluwarsa', 'bg-gray-100 text-gray-500'],
            'no_show' => ['Tidak hadir', 'bg-red-100 text-red-800'],
        ],
        // rentals.payment_status
        'payment' => [
            'unpaid' => ['Belum dibayar', 'bg-yellow-100 text-yellow-800'],
            'dp_paid' => ['DP terbayar', 'bg-blue-100 text-blue-800'],
            'fully_paid' => ['Lunas', 'bg-green-100 text-green-800'],
            'refunded' => ['Dikembalikan', 'bg-gray-100 text-gray-700'],
        ],
        // payments.payment_status
        'transaction' => [
            'pending' => ['Menunggu', 'bg-yellow-100 text-yellow-800'],
            'settlement' => ['Berhasil', 'bg-green-100 text-green-800'],
            'expire' => ['Kedaluwarsa', 'bg-gray-100 text-gray-500'],
            'cancel' => ['Dibatalkan', 'bg-red-100 text-red-800'],
            'deny' => ['Ditolak', 'bg-red-100 text-red-800'],
            'refund' => ['Refund', 'bg-gray-100 text-gray-700'],
        ],
        // verifications.status
        'verification' => [
            'pending' => ['Menunggu verifikasi', 'bg-yellow-100 text-yellow-800'],
            'approved' => ['Disetujui', 'bg-green-100 text-green-800'],
            'rejected' => ['Ditolak', 'bg-red-100 text-red-800'],
        ],
    ];

    [$label, $class] = $maps[$type][$status] ?? [$status, 'bg-gray-100 text-gray-700'];
@endphp

<span {{ $attributes->merge(['class' => 'inline-block rounded-full px-2.5 py-0.5 text-xs font-medium ' . $class]) }}>{{ $label }}</span>
