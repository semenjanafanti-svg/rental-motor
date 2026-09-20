@extends('layouts.public')

@section('title', 'Pesanan ' . $rental->booking_code)

@php
    $typeLabels = ['dp' => 'DP', 'balance' => 'Pelunasan', 'fine' => 'Denda'];
    $methodLabels = ['midtrans' => 'Midtrans', 'cash' => 'Tunai', 'manual_transfer' => 'QRIS (manual)'];
@endphp

@section('content')
    <a href="{{ route('rentals.index') }}" class="text-sm text-indigo-600 hover:underline">&larr; Kembali ke riwayat</a>

    <div class="mt-4 flex flex-wrap items-center gap-3">
        <h1 class="text-2xl font-semibold">{{ $rental->booking_code }}</h1>
        <x-status-badge :status="$rental->status" />
        <x-status-badge :status="$rental->payment_status" type="payment" />
    </div>

    @php $dp = $rental->payments->firstWhere('type', 'dp'); @endphp

    @if ($rental->status === 'pending_payment' && $rental->expires_at)
        @if ($payable)
            <div class="mt-4 rounded-md border border-yellow-200 bg-yellow-50 px-4 py-4 text-sm text-yellow-900">
                @if ($dp?->rejection_reason)
                    <p class="mb-3 rounded-md border border-red-200 bg-red-50 px-3 py-2 text-red-800">
                        Bukti pembayaran sebelumnya ditolak: {{ $dp->rejection_reason }}. Silakan unggah ulang.
                    </p>
                @endif

                <p>
                    Bayar DP sebesar <strong>{{ \App\Support\Format::rupiah($rental->dp_amount) }}</strong> via QRIS
                    sebelum <strong>{{ $rental->expires_at->locale('id')->translatedFormat('d M Y H:i') }} WIB</strong>,
                    lalu unggah bukti pembayarannya. Jika terlambat, pesanan kedaluwarsa.
                </p>

                <img src="{{ $qrisImage }}" alt="QRIS pembayaran"
                     class="mt-3 h-64 w-64 rounded border border-gray-200 bg-white object-contain">
                <p class="mt-2 font-medium">Isi nominal persis {{ \App\Support\Format::rupiah($rental->dp_amount) }}.</p>

                <form method="POST" action="{{ route('rentals.proof', $rental) }}" enctype="multipart/form-data" class="mt-4">
                    @csrf
                    <label for="proof_photo" class="mb-1 block font-medium">Bukti pembayaran</label>
                    <input type="file" id="proof_photo" name="proof_photo" accept="image/png,image/jpeg" required class="w-full text-sm">
                    @error('proof_photo')<p class="mt-1 text-red-700">{{ $message }}</p>@enderror
                    <p class="mt-1 text-xs text-yellow-800">JPG atau PNG, maksimal 4 MB.</p>

                    <button type="submit"
                            class="mt-3 rounded-md bg-indigo-600 px-4 py-2 font-medium text-white hover:bg-indigo-700">
                        Kirim bukti pembayaran
                    </button>
                </form>
            </div>
        @else
            <div class="mt-4 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                Batas waktu pembayaran DP sudah lewat. Pesanan akan kedaluwarsa otomatis dan jadwal dilepas.
            </div>
        @endif
    @endif

    @if ($rental->status === 'pending_verification')
        <div class="mt-4 rounded-md border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-800">
            Bukti pembayaran dan dokumen Anda sedang diverifikasi oleh petugas.
        </div>
    @endif

    @if ($rental->status === 'approved')
        <div class="mt-4 rounded-md border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">
            DP diterima dan dokumen disetujui. Datang pada jadwal mulai dan lunasi sisa pembayaran di lokasi.
        </div>
    @endif

    <div class="mt-6 grid gap-6 lg:grid-cols-2">
        <div class="rounded-lg border border-gray-200 bg-white p-6">
            <h2 class="mb-3 font-semibold">Detail sewa</h2>
            <dl class="grid grid-cols-2 gap-y-2 text-sm">
                <dt class="text-gray-500">Motor</dt>
                <dd>{{ $rental->bike->name }} ({{ $rental->bike->license_plate }})</dd>
                <dt class="text-gray-500">Mulai</dt>
                <dd>{{ $rental->start_time->locale('id')->translatedFormat('d M Y H:i') }} WIB</dd>
                <dt class="text-gray-500">Batas kembali</dt>
                <dd>{{ $rental->end_time->locale('id')->translatedFormat('d M Y H:i') }} WIB</dd>
                <dt class="text-gray-500">Durasi</dt>
                <dd>{{ intdiv($rental->total_hours, 24) }} hari ({{ $rental->total_hours }} jam)</dd>
                <dt class="text-gray-500">Tarif per 24 jam</dt>
                <dd>{{ \App\Support\Format::rupiah($rental->daily_rate_applied) }}</dd>
                <dt class="text-gray-500">Denda terlambat / jam</dt>
                <dd>{{ \App\Support\Format::rupiah($rental->hourly_rate_applied) }}</dd>
            </dl>
        </div>

        <div class="rounded-lg border border-gray-200 bg-white p-6">
            <h2 class="mb-3 font-semibold">Pembayaran</h2>
            <dl class="grid grid-cols-2 gap-y-2 text-sm">
                <dt class="text-gray-500">Total harga</dt>
                <dd>{{ \App\Support\Format::rupiah($rental->total_price) }}</dd>
                <dt class="text-gray-500">DP</dt>
                <dd>{{ \App\Support\Format::rupiah($rental->dp_amount) }}</dd>
                <dt class="font-medium">Sisa pelunasan</dt>
                <dd class="font-medium">{{ \App\Support\Format::rupiah($rental->balance_amount) }}</dd>
            </dl>
            @if ((float) $rental->balance_amount > 0)
                <p class="mt-3 text-xs text-gray-500">Sisa pelunasan dibayar di lokasi saat serah terima (tunai atau QRIS).</p>
            @endif
        </div>
    </div>

    @if ($rental->verification)
        <div class="mt-6 rounded-lg border border-gray-200 bg-white p-6">
            <div class="flex items-center gap-3">
                <h2 class="font-semibold">Verifikasi dokumen</h2>
                <x-status-badge :status="$rental->verification->status" type="verification" />
            </div>
            @if ($rental->verification->status === 'rejected' && $rental->verification->rejection_reason)
                <p class="mt-2 text-sm text-red-700">Alasan penolakan: {{ $rental->verification->rejection_reason }}</p>
            @endif
        </div>
    @endif

    <div class="mt-6 overflow-x-auto rounded-lg border border-gray-200 bg-white">
        <table class="min-w-full text-sm">
            <thead class="bg-gray-50 text-left text-xs uppercase tracking-wide text-gray-500">
                <tr>
                    <th class="px-4 py-3">Order ID</th>
                    <th class="px-4 py-3">Jenis</th>
                    <th class="px-4 py-3">Metode</th>
                    <th class="px-4 py-3">Nominal</th>
                    <th class="px-4 py-3">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($rental->payments as $payment)
                    <tr>
                        <td class="px-4 py-3">{{ $payment->order_id }}</td>
                        <td class="px-4 py-3">{{ $typeLabels[$payment->type] ?? $payment->type }}</td>
                        <td class="px-4 py-3">{{ $methodLabels[$payment->method] ?? $payment->method }}</td>
                        <td class="px-4 py-3">{{ \App\Support\Format::rupiah($payment->gross_amount) }}</td>
                        <td class="px-4 py-3"><x-status-badge :status="$payment->payment_status" type="transaction" /></td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-6 text-center text-gray-500">Belum ada pembayaran.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection
