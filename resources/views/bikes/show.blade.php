@extends('layouts.public')

@section('title', $bike->name)

@section('content')
    <nav class="breadcrumb" aria-label="Breadcrumb">
        <a href="{{ route('bikes.index') }}" class="link">Katalog</a>
        <span aria-hidden="true">/</span>
        <b class="text-ink">{{ $bike->name }}</b>
    </nav>

    <div class="flex flex-wrap gap-5">
        <div class="h-32 w-44 shrink-0 overflow-hidden rounded-lg border border-line">
            @if ($bike->photo)
                <img src="{{ asset('storage/' . $bike->photo) }}" alt="{{ $bike->name }}" class="h-full w-full object-cover">
            @else
                <x-bike-icon :category="$bike->category" :size="64" />
            @endif
        </div>

        <div>
            <h1 class="page-title">{{ $bike->name }}</h1>
            <p class="mt-0.5 text-sm text-muted">{{ $bike->brand }} - Plat {{ $bike->license_plate }}</p>

            <div class="mt-2.5 flex flex-wrap items-center gap-x-5 gap-y-1.5 text-[13px] text-muted">
                @if ($bike->cc)<span><b class="text-ink">{{ $bike->cc }}cc</b> mesin</span>@endif
                @if ($bike->year)<span><b class="text-ink">{{ $bike->year }}</b> tahun</span>@endif
                <span><b class="text-ink">{{ \App\Support\Format::rupiah($bike->daily_rate) }}</b> / 24 jam</span>
                <span>Denda telat <b class="text-ink">{{ \App\Support\Format::rupiah($bike->hourly_rate) }}</b> / jam</span>
                @if ($bike->status === 'available')
                    <span class="badge badge-teal badge-dot">Tersedia</span>
                @else
                    <span class="badge badge-rust badge-dot">Tidak tersedia</span>
                @endif
            </div>
        </div>
    </div>

    <div class="mt-7 grid items-start gap-6 lg:grid-cols-[1.2fr_0.8fr]">
        {{-- Kiri: ketersediaan + ketentuan --}}
        <div>
            <h2 class="section-title mt-0">Kalender ketersediaan</h2>
            <div class="card p-4">
                {{-- Input disembunyikan; Flatpickr menampilkan kalender inline di sebelahnya --}}
                <input type="text" id="availability-calendar" style="display:none"
                       data-url="{{ route('bikes.availability', $bike, false) }}">

                <p class="mt-3 flex items-center gap-2 text-xs text-muted">
                    <span class="inline-block h-3 w-3 rounded-[3px] bg-rust-bg"></span>
                    Ada jadwal terisi pada tanggal ini (sebagian atau seluruh hari)
                </p>

                <h3 class="mb-2 mt-4 text-sm font-semibold">Jadwal terisi (90 hari ke depan)</h3>
                <ul id="booked-list" class="list-disc space-y-1 pl-5 text-sm text-muted">
                    <li>Memuat jadwal...</li>
                </ul>
            </div>
        </div>

        {{-- Kanan: form jadwal --}}
        <div>
            <h2 class="section-title mt-0">Atur jadwal sewa</h2>

            @if ($bike->status !== 'available')
                <div class="notice">Motor ini sedang tidak tersedia untuk disewa.</div>
            @elseif (auth()->check() && auth()->user()->role !== 'customer')
                <div class="notice">Akun staf tidak dapat membuat pemesanan.</div>
            @else
                {{-- Form GET: jadwal dikirim lewat query string ke halaman checkout --}}
                <form id="schedule-form" method="GET" action="{{ route('bookings.create', $bike) }}"
                      data-dates-url="{{ route('bikes.dates', $bike, false) }}"
                      data-quote-url="{{ route('bikes.quote', $bike, false) }}"
                      data-min-days="{{ $minDays }}" data-max-days="{{ $maxDays }}"
                      data-open-hour="{{ config('rental.open_hour') }}" data-close-hour="{{ config('rental.close_hour') }}"
                      class="summary space-y-4">

                    <div>
                        <label for="start_time" class="label">Jam mulai</label>
                        <input type="text" id="start_time" name="start_time"
                               value="{{ $schedule['start_time'] ?? '08:00' }}"
                               autocomplete="off" required class="input">
                        <p class="hint">Jam kembali otomatis sama dengan jam mulai.</p>
                    </div>

                    <div>
                        <label for="start_date" class="label">Tanggal mulai</label>
                        <input type="text" id="start_date" name="start_date"
                               value="{{ $schedule['start_date'] ?? '' }}"
                               autocomplete="off" required placeholder="Pilih tanggal yang tersedia" class="input">
                        <p class="hint">Hanya tanggal yang tersedia pada jam mulai tersebut yang bisa dipilih.</p>
                    </div>

                    <div>
                        <label for="end_date" class="label">Tanggal kembali</label>
                        <input type="text" id="end_date" name="end_date"
                               value="{{ $schedule['end_date'] ?? '' }}"
                               autocomplete="off" required placeholder="Pilih tanggal kembali" class="input">
                        <p class="hint">Minimal {{ $minDays }} hari, maksimal {{ $maxDays }} hari.</p>
                    </div>

                    {{-- ID elemen di bawah dipakai oleh resources/js/rental.js. Jangan beri kelas display pada elemen yang di-toggle "hidden". --}}
                    <div id="quote-box" class="border-t border-line pt-3">
                        <p id="quote-placeholder" class="text-sm text-muted">
                            Pilih jam mulai, tanggal mulai, dan tanggal kembali untuk melihat harga.
                        </p>
                        <p id="quote-error" class="hidden text-sm text-rust" role="alert"></p>

                        <dl id="quote-ok" class="hidden">
                            <div class="summary-row"><dt>Mulai</dt><dd id="q-start"></dd></div>
                            <div class="summary-row"><dt>Kembali</dt><dd id="q-end"></dd></div>
                            <div class="summary-row"><dt>Durasi</dt><dd id="q-days"></dd></div>
                            <div class="summary-row"><dt>Total harga</dt><dd id="q-total"></dd></div>
                            <div class="summary-row"><dt>DP ({{ $dpPercent }}%) dibayar sekarang</dt><dd id="q-dp" class="font-semibold"></dd></div>
                            <div class="summary-row summary-total"><dt>Sisa dibayar saat ambil motor</dt><dd id="q-balance"></dd></div>
                        </dl>
                    </div>

                    <button type="submit" disabled class="btn btn-amber btn-block">Sewa Sekarang</button>

                    @guest
                        <p class="text-center text-xs text-muted">
                            Kamu akan diminta masuk atau mendaftar dulu. Jadwal yang dipilih tetap tersimpan.
                        </p>
                    @endguest
                </form>
            @endif
        </div>
    </div>

    {{-- Di bawah grid agar di ponsel urutannya: kalender, form jadwal, lalu ketentuan --}}
    <div class="mt-2 max-w-2xl">
        <h2 class="section-title">Ketentuan sewa</h2>
        <x-rental-policy :bike="$bike" />
    </div>
@endsection
