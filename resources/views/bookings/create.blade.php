@extends('layouts.public')

@section('title', 'Checkout ' . $bike->name)

@php
    $fmt = fn ($value) => \Carbon\Carbon::parse($value)->locale('id')->translatedFormat('d M Y, H:i');
@endphp

@section('content')
    <div class="mx-auto max-w-2xl">
        <nav class="breadcrumb" aria-label="Breadcrumb">
            <a href="{{ route('bikes.index') }}" class="link">Katalog</a>
            <span aria-hidden="true">/</span>
            <a href="{{ route('bikes.show', ['bike' => $bike] + $schedule) }}" class="link">{{ $bike->name }}</a>
            <span aria-hidden="true">/</span>
            <b class="text-ink">Checkout</b>
        </nav>

        <h1 class="page-title">Checkout</h1>
        <p class="page-sub">Periksa ringkasan pesanan, lalu unggah dokumen untuk diverifikasi.</p>

        <form method="POST" action="{{ route('bookings.store', $bike) }}" enctype="multipart/form-data">
            @csrf
            <input type="hidden" name="start_date" value="{{ $schedule['start_date'] }}">
            <input type="hidden" name="start_time" value="{{ $schedule['start_time'] }}">
            <input type="hidden" name="end_date" value="{{ $schedule['end_date'] }}">

            <h2 class="section-title mt-0">Ringkasan jadwal &amp; harga</h2>
            <dl class="summary">
                <div class="summary-row"><dt>Motor</dt><dd>{{ $bike->name }} ({{ $bike->license_plate }})</dd></div>
                <div class="summary-row"><dt>Mulai</dt><dd>{{ $fmt($quote['start_at']) }}</dd></div>
                <div class="summary-row"><dt>Kembali</dt><dd>{{ $fmt($quote['end_at']) }}</dd></div>
                <div class="summary-row"><dt>Durasi</dt><dd>{{ $quote['days'] }} hari ({{ $quote['total_hours'] }} jam)</dd></div>
                <div class="summary-row"><dt>Total sewa</dt><dd>{{ \App\Support\Format::rupiah($quote['total_price']) }}</dd></div>
                <div class="summary-row summary-total"><dt>DP dibayar sekarang</dt><dd>{{ \App\Support\Format::rupiah($quote['dp_amount']) }}</dd></div>
                <div class="summary-row"><dt>Sisa dibayar di lokasi</dt><dd>{{ \App\Support\Format::rupiah($quote['balance_amount']) }}</dd></div>
            </dl>

            <div class="notice mt-4">
                <b>Setelah menekan Buat Pesanan, DP harus dibayar dalam {{ $lockMinutes }} menit.</b>
                <p class="hint mb-0">Jika lewat, pesanan hangus dan jadwal dilepas untuk penyewa lain. Harga dihitung ulang oleh server.</p>
            </div>

            <h2 class="section-title">Unggah dokumen</h2>

            <div class="mb-4" x-data="{ file: null }">
                <label for="ktp_photo" class="label">Foto KTP</label>
                <div class="filepick" :class="{ 'filepick-filled': file }">
                    <input type="file" id="ktp_photo" name="ktp_photo" accept="image/png,image/jpeg" required
                           @change="file = $event.target.files[0] ? $event.target.files[0].name : null">
                    <span x-text="file ? '📎 ' + file : 'Ketuk untuk memilih foto KTP (JPG/PNG)'">Ketuk untuk memilih foto KTP (JPG/PNG)</span>
                </div>
                @error('ktp_photo')<p class="field-error">{{ $message }}</p>@enderror
            </div>

            <div class="mb-4" x-data="{ file: null }">
                <label for="sim_photo" class="label">Foto SIM C</label>
                <div class="filepick" :class="{ 'filepick-filled': file }">
                    <input type="file" id="sim_photo" name="sim_photo" accept="image/png,image/jpeg" required
                           @change="file = $event.target.files[0] ? $event.target.files[0].name : null">
                    <span x-text="file ? '📎 ' + file : 'Ketuk untuk memilih foto SIM C (JPG/PNG)'">Ketuk untuk memilih foto SIM C (JPG/PNG)</span>
                </div>
                @error('sim_photo')<p class="field-error">{{ $message }}</p>@enderror
            </div>

            @foreach (['start_date', 'start_time', 'end_date'] as $field)
                @error($field)<p class="field-error mb-2">{{ $message }}</p>@enderror
            @endforeach

            <p class="hint mb-4">
                Maksimal 2 MB per file. Dokumen disimpan di server privat dan hanya dapat dilihat admin yang memverifikasi.
            </p>

            <button type="submit" class="btn btn-amber btn-block">Buat Pesanan</button>
        </form>

        <div class="mt-7">
            <x-rental-policy :bike="$bike" />
        </div>
    </div>
@endsection
