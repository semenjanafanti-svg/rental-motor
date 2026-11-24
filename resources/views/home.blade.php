@extends('layouts.public')

@section('title', 'Sewa Motor')

@php
    $minRate = $featuredBikes->min('daily_rate');
    $whatsapp = config('rental.whatsapp');
@endphp

@section('content')
    {{-- ============ HERO (kartu krem kuning) ============ --}}
    <section class="card-dark grid gap-8 rounded-[2rem] p-6 sm:p-8 lg:grid-cols-[1.05fr_0.95fr] lg:items-center lg:p-10">
        <div class="flex flex-col gap-6">
            <p class="inline-flex w-fit items-center gap-2 rounded-full bg-night-2 px-4 py-2 text-xs font-semibold text-night-muted">
                <span class="h-2 w-2 rounded-full bg-volt" aria-hidden="true"></span>
                {{ config('rental.business_name') }} · Rental motor harian
            </p>
            <h1 class="max-w-xl text-4xl font-semibold leading-[1.05] sm:text-5xl lg:text-[56px]">Motor siap jalan, urusan sewa kami bikin gampang.</h1>
            <p class="max-w-lg text-base leading-7 text-night-muted">
                Untuk liburan, kuliah, atau urusan kerja. Pilih unit, cek jadwal, lalu pesan online dengan DP {{ config('rental.dp_percent') }}% via QRIS.
            </p>
            <div class="flex flex-wrap items-center gap-x-5 gap-y-3">
                <a href="{{ route('bikes.index') }}" class="btn btn-volt px-6 py-3 text-[15px]">
                    Pilih motor
                    <svg aria-hidden="true" viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14m-6-6 6 6-6 6"/></svg>
                </a>
                @if ($minRate)
                    <span class="text-sm text-night-muted">Mulai <strong class="text-ink">{{ \App\Support\Format::rupiah($minRate) }}</strong> / 24 jam</span>
                @endif
            </div>

            <ul class="grid gap-2.5 pt-2 sm:grid-cols-3">
                @foreach ([
                    ['Fasilitas lengkap', 'Helm, STNK, jas hujan', 'M3 7.5 12 3l9 4.5-9 4.5-9-4.5Zm0 0v9l9 4.5 9-4.5v-9M12 12v9'],
                    ['DP '.config('rental.dp_percent').'%', 'Bayar praktis via QRIS', 'm13 2-8 12h6l-1 8 9-13h-6l1-7Z'],
                    ['Jadwal jelas', 'Cek kalender dulu', 'M8 3v4m8-4v4M3 10h18M5 5h14a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2Z'],
                ] as [$title, $text, $path])
                    <li class="flex items-center gap-3 rounded-2xl bg-night-2 p-3 sm:flex-col sm:items-start lg:p-3.5">
                        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-surface text-ink">
                            <svg aria-hidden="true" viewBox="0 0 24 24" class="h-[18px] w-[18px]" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $path }}"/></svg>
                        </span>
                        <span><strong class="block text-sm">{{ $title }}</strong><span class="block text-[11.5px] text-night-muted">{{ $text }}</span></span>
                    </li>
                @endforeach
            </ul>
        </div>

        <div class="relative h-72 overflow-hidden rounded-[1.5rem] sm:h-96 lg:h-[30rem]">
            <img src="{{ asset('images/hero-rental.jpg') }}" alt="Dua pengendara menikmati perjalanan dengan motor" fetchpriority="high"
                 class="absolute inset-0 h-full w-full object-cover object-[center_62%]">
            <div class="absolute bottom-4 left-4 right-4 flex items-center gap-3 rounded-2xl bg-white p-3 text-ink sm:right-auto">
                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-volt text-volt-ink">
                    <svg aria-hidden="true" viewBox="0 0 24 24" class="h-5 w-5" fill="currentColor"><path d="m13.2 2-8 11h5.6L9.9 22l8.9-12h-5.9L13.2 2Z"/></svg>
                </span>
                <span><strong class="block text-sm">Pesan dalam hitungan menit</strong><span class="block text-xs text-muted">DP QRIS · Konfirmasi jadwal</span></span>
            </div>
        </div>
    </section>

    {{-- ============ LANGKAH ============ --}}
    <section class="grid gap-8 py-16 md:grid-cols-[0.8fr_1.2fr] md:items-start">
        <div>
            <p class="eyebrow">Tiga langkah, langsung berangkat</p>
            <h2 class="mt-2 max-w-sm text-3xl font-semibold leading-tight">Pesan dari rumah. Ambil motor saat waktunya.</h2>
        </div>
        <ol class="grid gap-3 sm:grid-cols-3">
            @foreach ([['01', 'Pilih motor', 'Cek harga dan kalendernya. Pilih jam yang cocok.'], ['02', 'Lengkapi data', 'Isi jadwal, unggah KTP dan SIM C, lalu bayar DP via QRIS.'], ['03', 'Ambil kunci', 'Datang sesuai jadwal. Sisa pembayaran dilunasi saat serah terima.']] as [$number, $title, $description])
                <li class="card p-5">
                    <span class="inline-flex h-9 w-9 items-center justify-center rounded-full bg-volt text-sm font-semibold text-volt-ink">{{ $number }}</span>
                    <h3 class="mt-4 text-base font-semibold">{{ $title }}</h3>
                    <p class="mt-1 text-sm leading-6 text-muted">{{ $description }}</p>
                </li>
            @endforeach
        </ol>
    </section>

    {{-- ============ ARMADA ============ --}}
    <section>
        <div class="mb-6 flex flex-wrap items-end justify-between gap-3">
            <div>
                <p class="eyebrow">Pilih sesuai perjalanan</p>
                <h2 class="mt-2 text-3xl font-bold leading-tight">Armada yang ada sekarang</h2>
            </div>
            <a href="{{ route('bikes.index') }}" class="btn btn-outline">Buka katalog <span aria-hidden="true">→</span></a>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            @forelse ($featuredBikes as $bike)
                <x-bike-card :bike="$bike" />
            @empty
                <p class="card p-6 text-sm text-muted sm:col-span-2 lg:col-span-4">Armada sedang diperbarui. Coba cek katalog lagi sebentar.</p>
            @endforelse
        </div>
    </section>

    {{-- ============ KETENTUAN + KONTAK ============ --}}
    <section class="grid gap-5 pt-16 lg:grid-cols-[1.2fr_0.8fr]">
        <div class="card p-6 sm:p-8">
            <p class="eyebrow">Biar sama-sama enak</p>
            <h2 class="mt-2 text-2xl font-bold leading-tight sm:text-3xl">Sebelum kunci dibawa, perhatikan ketentuan berikut</h2>
            <x-rental-policy class="mt-5 !text-[15px] leading-7" />
        </div>

        <aside class="card flex flex-col gap-6 p-6 sm:p-8">
            <div>
                <p class="eyebrow">Mampir langsung</p>
                <h2 class="mt-2 text-2xl font-bold leading-tight">Ada yang mau ditanyakan?</h2>
                <p class="mt-2 text-sm leading-6 text-muted">Tanya jadwal, titik pengambilan, atau rekomendasi motor buat rute kamu.</p>
            </div>
            <dl class="space-y-4 border-t border-line pt-5 text-sm">
                <div>
                    <dt class="text-xs font-semibold text-muted">Kontak</dt>
                    <dd class="mt-1 text-base font-semibold">{{ $whatsapp ? '+'.$whatsapp : '0812-3456-7890' }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold text-muted">Jam layanan</dt>
                    <dd class="mt-1 text-base font-semibold">{{ config('rental.operating_hours') ?: sprintf('Serah terima motor %02d.00–%02d.00 WIB', config('rental.open_hour'), config('rental.close_hour')) }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold text-muted">Alamat</dt>
                    <dd class="mt-1 text-base font-semibold leading-6">{{ config('rental.address') ?: 'Jl. Pemuda Kelompok C No. A2, Airlangga, Kec. Mulyorejo, Kota Surabaya, Jawa Timur 60115' }}</dd>
                </div>
            </dl>
            @if ($whatsapp)
                <a href="https://wa.me/{{ $whatsapp }}?text={{ rawurlencode('Halo '.config('rental.business_name').', saya mau tanya soal sewa motor.') }}" class="btn w-fit" target="_blank" rel="noopener">
                    <svg aria-hidden="true" viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M20 11.5a8.4 8.4 0 0 1-12.4 7.4L4 20l1.2-3.4A8.4 8.4 0 1 1 20 11.5Z"/></svg>
                    Chat WhatsApp
                </a>
            @endif
        </aside>
    </section>
@endsection
