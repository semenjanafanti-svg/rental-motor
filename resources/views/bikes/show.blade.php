@extends('layouts.public')

@section('title', $bike->name)

@php
    $facilityLabels = ['helm' => 'Helm', 'stnk' => 'STNK', 'jas_hujan' => 'Jas hujan', 'phone_holder' => 'Phone holder'];
    $specs = array_filter([
        $bike->cc ? ['Mesin', $bike->cc.' cc', 'M4 13h3l2-6h6l2 6h3M6 17a2 2 0 1 0 0-4 2 2 0 0 0 0 4Zm12 0a2 2 0 1 0 0-4 2 2 0 0 0 0 4Z'] : null,
        $bike->year ? ['Tahun', $bike->year, 'M8 3v4m8-4v4M3 10h18M5 5h14a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2Z'] : null,
        $bike->color ? ['Warna', $bike->color, 'M12 21a9 9 0 1 1 9-9c0 2-1.5 3-3 3h-2a2 2 0 0 0-1 3.7c.5.4.6 1.1.2 1.6-.5.5-1.4.7-3.2.7Z'] : null,
        ['Denda telat', \App\Support\Format::rupiah($bike->hourly_rate).'/jam', 'M12 7v5l3 2m6-2a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z'],
    ]);
@endphp

@section('content')
    <nav class="breadcrumb" aria-label="Breadcrumb">
        <a href="{{ route('bikes.index') }}" class="link">Katalog</a>
        <span aria-hidden="true">/</span>
        <b class="text-ink">{{ $bike->name }}</b>
    </nav>

    <div class="grid items-start gap-6 lg:grid-cols-[1.2fr_0.8fr]">
        {{-- Kiri: foto, spesifikasi, kalender --}}
        <div class="min-w-0 space-y-5">
            <section class="card-dark p-2.5">
                <div class="h-64 overflow-hidden rounded-[1.25rem] bg-paper sm:h-80">
                    @if ($bike->hasPhoto())
                        <img src="{{ $bike->photoUrl() }}" alt="{{ $bike->name }}" class="h-full w-full object-cover">
                    @else
                        <x-bike-icon :category="$bike->category" :size="120" />
                    @endif
                </div>
                <div class="flex flex-wrap items-end justify-between gap-3 px-3 pb-3 pt-5">
                    <div>
                        <p class="text-xs capitalize text-night-muted">{{ $bike->category }} · {{ $bike->brand }} · Plat {{ $bike->license_plate }}</p>
                        <h1 class="mt-1 text-3xl font-semibold leading-tight">{{ $bike->name }}</h1>
                        <div class="mt-2.5">
                            @if ($isActivelyRented)
                                <span class="badge badge-rust badge-dot">Sedang disewa</span>
                            @elseif ($bike->status === 'available')
                                <span class="badge badge-teal badge-dot">Tersedia</span>
                            @else
                                <span class="badge badge-rust badge-dot">Tidak tersedia</span>
                            @endif
                        </div>
                    </div>
                    <p class="text-right">
                        <span class="block text-2xl font-semibold text-ink">{{ \App\Support\Format::rupiah($bike->daily_rate) }}</span>
                        <span class="text-xs text-night-muted">per 24 jam</span>
                    </p>
                </div>
            </section>

            <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                @foreach ($specs as [$label, $value, $path])
                    <div class="spec">
                        <span class="spec-icon"><svg aria-hidden="true" viewBox="0 0 24 24" class="h-[18px] w-[18px]" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $path }}"/></svg></span>
                        <span class="spec-label">{{ $label }}</span>
                        <span class="spec-value">{{ $value }}</span>
                    </div>
                @endforeach
            </div>

            <section>
                <h2 class="section-title mt-2">Kalender ketersediaan</h2>
                <div class="card p-4">
                    {{-- Input disembunyikan; Flatpickr menampilkan kalender inline di sebelahnya --}}
                    <input type="text" id="availability-calendar" style="display:none"
                           data-today="{{ $today }}"
                           data-url="{{ route('bikes.availability', $bike, false) }}">

                    <p class="mt-3 flex items-center gap-2 text-xs text-muted">
                        <span class="inline-block h-3 w-3 rounded-full bg-rust-bg ring-1 ring-rust"></span>
                        Tanggal berwarna memiliki jadwal sewa.
                    </p>
                </div>
            </section>
        </div>

        {{-- Kanan: form jadwal --}}
        <div class="lg:sticky lg:top-28">
            <h2 class="section-title mt-0">Atur jadwal sewa</h2>

            @if ($isOverdue)
                <div class="notice">Motor belum dikembalikan oleh penyewa sebelumnya, jadi belum bisa dipesan. Coba lagi nanti.</div>
            @elseif ($bike->status !== 'available')
                <div class="notice">Motor ini sedang tidak tersedia untuk disewa.</div>
            @elseif (auth()->check() && auth()->user()->role !== 'customer')
                <div class="notice">Akun staf tidak dapat membuat pemesanan.</div>
            @else
                @if ($isActivelyRented)
                    <div class="notice mb-4">Motor sedang disewa sampai {{ $activeUntil->translatedFormat('j M Y, H.i') }}. Kamu tetap bisa memesan untuk jadwal setelahnya.</div>
                @endif
                {{-- Form GET: jadwal dikirim lewat query string ke halaman checkout --}}
                <form id="schedule-form" method="GET" action="{{ route('bookings.create', $bike) }}"
                      data-dates-url="{{ route('bikes.dates', $bike, false) }}"
                      data-quote-url="{{ route('bikes.quote', $bike, false) }}"
                      data-min-days="{{ $minDays }}" data-max-days="{{ $maxDays }}"
                      data-today="{{ $today }}"
                      data-open-hour="{{ config('rental.open_hour') }}" data-close-hour="{{ config('rental.close_hour') }}"
                      class="card space-y-4 p-5">

                    <div>
                        <label for="start_time" class="label">Jam mulai</label>
                        <input type="text" id="start_time" name="start_time"
                               value="{{ $schedule['start_time'] ?? sprintf('%02d:00', config('rental.open_hour')) }}"
                               autocomplete="off" required class="input">
                        <p class="hint">Jam kembali otomatis sama dengan jam mulai.</p>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-1 xl:grid-cols-2">
                        <div>
                            <label for="start_date" class="label">Tanggal mulai</label>
                            <input type="text" id="start_date" name="start_date"
                                   value="{{ $schedule['start_date'] ?? '' }}"
                                   autocomplete="off" required placeholder="Pilih tanggal" class="input">
                            <p id="availability-loading" class="hint" role="status">Memuat tanggal tersedia...</p>
                        </div>

                        <div>
                            <label for="end_date" class="label">Tanggal kembali</label>
                            <input type="text" id="end_date" name="end_date"
                                   value="{{ $schedule['end_date'] ?? '' }}"
                                   autocomplete="off" required placeholder="Pilih tanggal" class="input">
                            <p class="hint">Minimal {{ $minDays }}, maksimal {{ $maxDays }} hari.</p>
                        </div>
                    </div>

                    {{-- ID elemen di bawah dipakai oleh resources/js/rental.js. Jangan beri kelas display pada elemen yang di-toggle "hidden". --}}
                    <div id="quote-box" class="rounded-2xl bg-paper p-4">
                        <p id="quote-placeholder" class="text-sm text-muted">
                            Pilih jam mulai, tanggal mulai, dan tanggal kembali untuk melihat harga.
                        </p>
                        <p id="quote-error" class="hidden text-sm text-rust" role="alert"></p>

                        <dl id="quote-ok" class="hidden">
                            <div class="summary-row"><dt>Mulai</dt><dd id="q-start"></dd></div>
                            <div class="summary-row"><dt>Kembali</dt><dd id="q-end"></dd></div>
                            <div class="summary-row"><dt>Durasi</dt><dd id="q-days"></dd></div>
                            <div class="summary-row"><dt>Total harga</dt><dd id="q-total"></dd></div>
                            <div class="summary-row"><dt>DP ({{ $dpPercent }}%) dibayar sekarang</dt><dd id="q-dp" class="font-bold"></dd></div>
                            <div class="summary-row summary-total"><dt>Sisa dibayar saat ambil motor</dt><dd id="q-balance"></dd></div>
                        </dl>
                    </div>

                    <button type="submit" disabled class="btn btn-volt btn-block py-3 text-[15px]">Sewa sekarang</button>

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
    <div class="mt-4 max-w-3xl">
        @if ($bike->facilities)
            <h2 class="section-title">Fasilitas termasuk</h2>
            <div class="flex flex-wrap gap-2">
                @foreach ($bike->facilities as $facility)
                    <span class="badge badge-neutral bg-surface px-3.5 py-2 text-[13px] text-ink">{{ $facilityLabels[$facility] ?? ucwords(str_replace('_', ' ', $facility)) }}</span>
                @endforeach
            </div>
        @endif
        <h2 class="section-title">Ketentuan sewa</h2>
        <div class="card p-5">
            <x-rental-policy :bike="$bike" />
        </div>
    </div>
@endsection
