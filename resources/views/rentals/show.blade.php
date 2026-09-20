@extends('layouts.public')

@section('title', 'Pesanan ' . $rental->booking_code)

@php
    $typeLabels = ['dp' => 'DP', 'balance' => 'Pelunasan', 'fine' => 'Denda'];
    $methodLabels = ['midtrans' => 'Midtrans', 'cash' => 'Tunai', 'manual_transfer' => 'Transfer manual'];
@endphp

@section('content')
    <a href="{{ route('rentals.index') }}" class="text-sm text-indigo-600 hover:underline">&larr; Kembali ke riwayat</a>

    <div class="mt-4 flex flex-wrap items-center gap-3">
        <h1 class="text-2xl font-semibold">{{ $rental->booking_code }}</h1>
        <x-status-badge :status="$rental->status" />
        <x-status-badge :status="$rental->payment_status" type="payment" />
    </div>

    @if ($rental->status === 'pending_payment' && $rental->expires_at)
        @if ($payable)
            <div class="mt-4 rounded-md border border-yellow-200 bg-yellow-50 px-4 py-4 text-sm text-yellow-900">
                <p>
                    Selesaikan pembayaran DP {{ \App\Support\Format::rupiah($rental->dp_amount) }} sebelum
                    <strong>{{ $rental->expires_at->locale('id')->translatedFormat('d M Y H:i') }} WIB</strong>,
                    atau pesanan akan kedaluwarsa.
                </p>

                <button type="button" id="pay-button" data-pay-url="{{ route('rentals.pay', $rental, false) }}"
                        class="mt-3 rounded-md bg-indigo-600 px-4 py-2 font-medium text-white hover:bg-indigo-700 disabled:cursor-not-allowed disabled:bg-gray-300">
                    Bayar DP {{ \App\Support\Format::rupiah($rental->dp_amount) }}
                </button>
                <p id="pay-error" class="mt-2 hidden text-red-700"></p>
                <p class="mt-2 text-xs text-yellow-800">
                    Halaman ini otomatis diperbarui setelah pembayaran diterima. Jika popup tertutup, klik tombol lagi
                    untuk melihat instruksi pembayaran yang sama.
                </p>
            </div>

            <script src="{{ $snapJsUrl }}" data-client-key="{{ $clientKey }}"></script>
        @else
            <div class="mt-4 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                Batas waktu pembayaran DP sudah lewat. Pesanan akan kedaluwarsa otomatis dan jadwal dilepas.
            </div>
        @endif

        {{-- Polling status: halaman dimuat ulang saat status berubah --}}
        <div id="status-poll" class="hidden"
             data-url="{{ route('rentals.status', $rental, false) }}"
             data-status="{{ $rental->status }}"></div>
    @endif

    @if ($rental->status === 'pending_verification')
        <div class="mt-4 rounded-md border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">
            DP sudah kami terima. Dokumen Anda sedang diverifikasi oleh petugas.
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
