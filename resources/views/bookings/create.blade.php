@extends('layouts.public')

@section('title', 'Checkout ' . $bike->name)

@php
    $fmt = fn ($value) => \Carbon\Carbon::parse($value)->locale('id')->translatedFormat('d M Y H:i');
@endphp

@section('content')
    <a href="{{ route('bikes.show', ['bike' => $bike] + $schedule) }}" class="text-sm text-indigo-600 hover:underline">&larr; Ubah jadwal</a>

    <h1 class="mt-4 text-2xl font-semibold">Checkout {{ $bike->name }}</h1>

    <form method="POST" action="{{ route('bookings.store', $bike) }}" enctype="multipart/form-data"
          class="mt-6 grid gap-8 lg:grid-cols-2">
        @csrf
        <input type="hidden" name="start_date" value="{{ $schedule['start_date'] }}">
        <input type="hidden" name="start_time" value="{{ $schedule['start_time'] }}">
        <input type="hidden" name="end_date" value="{{ $schedule['end_date'] }}">

        <div class="space-y-5 rounded-lg border border-gray-200 bg-white p-6">
            <h2 class="font-semibold">Dokumen identitas</h2>

            <div>
                <label for="ktp_photo" class="mb-1 block text-sm font-medium">Foto KTP</label>
                <input type="file" id="ktp_photo" name="ktp_photo" accept="image/png,image/jpeg" required class="w-full text-sm">
                @error('ktp_photo')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>

            <div>
                <label for="sim_photo" class="mb-1 block text-sm font-medium">Foto SIM C</label>
                <input type="file" id="sim_photo" name="sim_photo" accept="image/png,image/jpeg" required class="w-full text-sm">
                @error('sim_photo')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>

            @foreach (['start_date', 'start_time', 'end_date'] as $field)
                @error($field)<p class="text-sm text-red-600">{{ $message }}</p>@enderror
            @endforeach

            <p class="text-xs text-gray-500">
                Format JPG atau PNG, maksimal 2 MB per file. Dokumen disimpan secara privat dan hanya dapat dilihat
                oleh petugas yang memverifikasi.
            </p>
        </div>

        <div class="space-y-4">
            <div class="rounded-lg border border-gray-200 bg-white p-6 text-sm">
                <h2 class="mb-3 font-semibold">Ringkasan sewa</h2>
                <dl class="space-y-2">
                    <div class="flex justify-between"><dt>Motor</dt><dd>{{ $bike->name }}</dd></div>
                    <div class="flex justify-between"><dt>Mulai</dt><dd>{{ $fmt($quote['start_at']) }}</dd></div>
                    <div class="flex justify-between"><dt>Kembali</dt><dd>{{ $fmt($quote['end_at']) }}</dd></div>
                    <div class="flex justify-between"><dt>Durasi</dt><dd>{{ $quote['days'] }} hari ({{ $quote['total_hours'] }} jam)</dd></div>
                    <div class="flex justify-between border-t pt-2"><dt>Total harga</dt><dd>{{ \App\Support\Format::rupiah($quote['total_price']) }}</dd></div>
                    <div class="flex justify-between font-semibold"><dt>DP (dibayar sekarang)</dt><dd>{{ \App\Support\Format::rupiah($quote['dp_amount']) }}</dd></div>
                    <div class="flex justify-between text-gray-600"><dt>Sisa pelunasan (di lokasi)</dt><dd>{{ \App\Support\Format::rupiah($quote['balance_amount']) }}</dd></div>
                </dl>
            </div>

            <div class="rounded-md border border-yellow-200 bg-yellow-50 px-4 py-3 text-xs text-yellow-800">
                Motor diserahkan dengan bensin penuh dan wajib dikembalikan dalam kondisi yang sama.
                Keterlambatan setelah jam pengembalian dikenai denda per jam.
            </div>

            <button type="submit" class="w-full rounded-md bg-indigo-600 px-4 py-3 font-medium text-white hover:bg-indigo-700">
                Buat Pesanan
            </button>

            <p class="text-xs text-gray-500">
                Setelah pesanan dibuat, Anda punya {{ $lockMinutes }} menit untuk membayar DP via QRIS dan mengunggah
                bukti pembayaran. Lewat dari itu pesanan kedaluwarsa otomatis. Harga dihitung ulang oleh server.
            </p>
        </div>
    </form>
@endsection
