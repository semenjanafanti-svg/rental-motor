@extends('layouts.public')

@section('title', 'Pesan ' . $bike->name)

@section('content')
    <a href="{{ route('bikes.show', $bike) }}" class="text-sm text-indigo-600 hover:underline">&larr; Kembali ke detail motor</a>

    <h1 class="mt-4 text-2xl font-semibold">Pesan {{ $bike->name }}</h1>
    <p class="text-sm text-gray-600">
        {{ \App\Support\Format::rupiah($bike->daily_rate) }} / 24 jam &middot;
        denda terlambat {{ \App\Support\Format::rupiah($bike->hourly_rate) }} / jam
    </p>

    <form id="checkout-form" method="POST" action="{{ route('bookings.store', $bike) }}" enctype="multipart/form-data"
          data-dates-url="{{ route('bikes.dates', $bike, false) }}"
          data-quote-url="{{ route('bikes.quote', $bike, false) }}"
          data-min-days="{{ $minDays }}" data-max-days="{{ $maxDays }}"
          class="mt-6 grid gap-8 lg:grid-cols-2">
        @csrf

        <div class="space-y-5 rounded-lg border border-gray-200 bg-white p-6">
            <h2 class="font-semibold">1. Jadwal sewa</h2>

            <div>
                <label for="start_time" class="mb-1 block text-sm font-medium">Jam mulai</label>
                <input type="text" id="start_time" name="start_time" value="{{ old('start_time', '08:00') }}"
                       autocomplete="off" required
                       class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm">
                <p class="mt-1 text-xs text-gray-500">Jam pengembalian otomatis sama dengan jam mulai.</p>
                @error('start_time')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>

            <div>
                <label for="start_date" class="mb-1 block text-sm font-medium">Tanggal mulai</label>
                <input type="text" id="start_date" name="start_date" value="{{ old('start_date') }}"
                       autocomplete="off" required placeholder="Pilih tanggal yang tersedia"
                       class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm">
                <p class="mt-1 text-xs text-gray-500">Hanya tanggal yang tersedia untuk motor ini pada jam mulai tersebut yang bisa dipilih.</p>
                @error('start_date')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>

            <div>
                <label for="end_date" class="mb-1 block text-sm font-medium">Tanggal pengembalian</label>
                <input type="text" id="end_date" name="end_date" value="{{ old('end_date') }}"
                       autocomplete="off" required placeholder="Pilih tanggal pengembalian"
                       class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm">
                <p class="mt-1 text-xs text-gray-500">
                    Minimal {{ $minDays }} hari ({{ $minDays * 24 }} jam), maksimal {{ $maxDays }} hari.
                    Tanggal setelah jadwal orang lain tidak bisa dipilih.
                </p>
                @error('end_date')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>

            <h2 class="pt-2 font-semibold">2. Dokumen identitas</h2>

            <div>
                <label for="ktp_photo" class="mb-1 block text-sm font-medium">Foto KTP</label>
                <input type="file" id="ktp_photo" name="ktp_photo" accept="image/png,image/jpeg" required
                       class="w-full text-sm">
                @error('ktp_photo')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>

            <div>
                <label for="sim_photo" class="mb-1 block text-sm font-medium">Foto SIM C</label>
                <input type="file" id="sim_photo" name="sim_photo" accept="image/png,image/jpeg" required
                       class="w-full text-sm">
                @error('sim_photo')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>

            <p class="text-xs text-gray-500">
                Format JPG atau PNG, maksimal 2 MB per file. Dokumen disimpan secara privat dan hanya dapat dilihat
                oleh petugas yang memverifikasi.
            </p>
        </div>

        <div class="space-y-4">
            <div id="quote-box" class="rounded-lg border border-gray-200 bg-white p-6 text-sm">
                <h2 class="mb-3 font-semibold">Ringkasan sewa</h2>

                <p id="quote-placeholder" class="text-gray-500">
                    Pilih jam mulai, tanggal mulai, dan tanggal pengembalian untuk melihat ringkasan.
                </p>
                <p id="quote-error" class="hidden text-red-600"></p>

                <dl id="quote-ok" class="hidden space-y-2">
                    <div class="flex justify-between"><dt>Mulai</dt><dd id="q-start"></dd></div>
                    <div class="flex justify-between"><dt>Kembali (otomatis)</dt><dd id="q-end"></dd></div>
                    <div class="flex justify-between"><dt>Durasi</dt><dd id="q-days"></dd></div>
                    <div class="flex justify-between border-t pt-2"><dt>Total harga</dt><dd id="q-total"></dd></div>
                    <div class="flex justify-between font-semibold">
                        <dt>DP (dibayar sekarang)</dt><dd id="q-dp"></dd>
                    </div>
                    <div class="flex justify-between text-gray-600">
                        <dt>Sisa pelunasan (di lokasi)</dt><dd id="q-balance"></dd>
                    </div>
                </dl>
            </div>

            <div class="rounded-md border border-yellow-200 bg-yellow-50 px-4 py-3 text-xs text-yellow-800">
                Motor diserahkan dengan bensin penuh dan wajib dikembalikan dalam kondisi yang sama.
                Keterlambatan setelah jam pengembalian dikenai denda per jam.
            </div>

            <button type="submit"
                    class="w-full rounded-md bg-indigo-600 px-4 py-3 font-medium text-white hover:bg-indigo-700 disabled:cursor-not-allowed disabled:bg-gray-300">
                Buat Pesanan
            </button>

            <p class="text-xs text-gray-500">
                Slot dikunci selama {{ $lockMinutes }} menit setelah pesanan dibuat. Pesanan yang DP-nya tidak dibayar
                dalam waktu tersebut akan kedaluwarsa otomatis. Harga dihitung ulang oleh server saat pesanan dibuat.
            </p>
        </div>
    </form>
@endsection
