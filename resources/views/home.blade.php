@extends('layouts.public')

@section('title', 'Sewa Motor')

@section('content')
    <section class="relative isolate grid gap-7 overflow-hidden rounded-[1.75rem] border border-[#f1e5c9] bg-gradient-to-br from-[#fff8e8] via-[#fffefa] to-[#fff0df] px-5 py-8 shadow-sm sm:px-7 lg:min-h-[31rem] lg:grid-cols-[1.08fr_0.92fr] lg:items-center lg:gap-9 lg:px-10 lg:py-9">
        <div class="pointer-events-none absolute -right-10 top-10 -z-10 h-56 w-56 rounded-full bg-gradient-to-br from-amber/30 to-orange-200/20 blur-3xl"></div>
        <div class="relative z-10 flex flex-col gap-5">
            <p class="inline-flex w-fit items-center gap-2 rounded-full border border-amber/50 bg-[#fff3cb] px-4 py-2 text-sm font-bold text-[#49220d]">
                <span>{{ config('rental.business_name') }} · Rental Motor Harian</span><span class="text-amber">·</span><span class="text-[#c94713]">Booking praktis</span>
            </p>
            <h1 class="max-w-3xl font-display text-5xl font-bold leading-[0.98] tracking-tight text-[#191917] sm:text-6xl lg:text-7xl">Sewa Motor <span class="bg-gradient-to-r from-[#f39812] via-[#ed6d24] to-[#d94b37] bg-clip-text text-transparent">Cepat,</span><br class="hidden sm:block"> Jalan Lebih Santai.</h1>
            <p class="max-w-2xl text-base leading-7 text-[#66665e] sm:text-lg sm:leading-8">Solusi sewa motor harian untuk liburan, kuliah, dan urusan kerja. Pilih unit, cek jadwal, lalu pesan online dengan <strong class="text-ink">DP 30% via QRIS</strong>.</p>
            <div class="flex flex-wrap items-center gap-4">
                <a href="{{ route('bikes.index') }}" class="btn btn-amber rounded-xl bg-gradient-to-r from-[#ffc928] to-[#ff941f] px-6 py-3.5 shadow-md shadow-orange-200 transition hover:-translate-y-0.5 hover:shadow-lg hover:shadow-orange-200">Pilih motor <span class="ml-2" aria-hidden="true">→</span></a>
                <span class="text-sm text-muted">Mulai <strong class="text-ink">{{ \App\Support\Format::rupiah($featuredBikes->min('daily_rate') ?? 0) }}</strong> / 24 jam</span>
            </div>
            <div class="grid gap-2.5 pt-1 sm:grid-cols-3">
                <div class="group flex min-h-20 items-center gap-2.5 rounded-2xl border border-[#e9e6dc] bg-white/90 p-3 shadow-sm transition duration-200 hover:-translate-y-1 hover:border-amber/50 hover:shadow-lg">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-[#d8f7e9] to-[#a9e8d2] text-teal"><svg aria-hidden="true" viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="m3 7.5 9-4.5 9 4.5-9 4.5-9-4.5Z"/><path stroke-linecap="round" stroke-linejoin="round" d="M3 7.5v9l9 4.5 9-4.5v-9M12 12v9"/></svg></span>
                    <span><strong class="block text-sm leading-5">Fasilitas lengkap</strong><span class="mt-1 block text-[11px] leading-4 text-muted">Helm · STNK · Jas hujan · Phone holder</span></span>
                </div>
                <div class="group flex min-h-20 items-center gap-2.5 rounded-2xl border border-[#e9e6dc] bg-white/90 p-3 shadow-sm transition duration-200 hover:-translate-y-1 hover:border-amber/50 hover:shadow-lg">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-[#fff1c4] text-[#bd6500]"><svg aria-hidden="true" viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="m13 2-8 12h6l-1 8 9-13h-6l1-7Z"/></svg></span>
                    <span><strong class="block text-sm leading-5">DP 30%</strong><span class="mt-1 block text-[11px] leading-4 text-muted">Pembayaran praktis</span></span>
                </div>
                <div class="group flex min-h-20 items-center gap-2.5 rounded-2xl border border-[#e9e6dc] bg-white/90 p-3 shadow-sm transition duration-200 hover:-translate-y-1 hover:border-amber/50 hover:shadow-lg">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-[#ffeadb] text-[#d45a1c]"><svg aria-hidden="true" viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M20 10c0 5-8 12-8 12S4 15 4 10a8 8 0 1 1 16 0Z"/><circle cx="12" cy="10" r="2.5"/></svg></span>
                    <span><strong class="block text-sm leading-5">Jadwal jelas</strong><span class="mt-1 block text-[11px] leading-4 text-muted">Cek kalender sebelum pesan</span></span>
                </div>
            </div>
        </div>

        <div class="relative mx-auto h-[21rem] w-full max-w-md overflow-visible sm:h-[25rem] lg:mt-5 lg:h-[27rem]">
            <div class="group absolute inset-0 overflow-hidden rounded-[1.75rem] border border-amber/50 bg-[#f2d59c] shadow-xl shadow-orange-950/10">
                <img src="{{ asset('images/hero-rental.jpg') }}" alt="Dua pengendara menikmati perjalanan dengan motor" fetchpriority="high" class="absolute inset-0 h-full w-full object-cover object-[center_62%] transition duration-700 group-hover:scale-105">
                <div class="absolute inset-0 bg-gradient-to-t from-black/30 via-transparent to-transparent"></div>
            </div>
            <div class="absolute -right-1 top-4 flex items-center gap-2.5 rounded-xl border border-amber/50 bg-white/95 px-3 py-2.5 shadow-xl sm:-right-4 sm:top-6 sm:px-4">
                <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-[#ff9b20] text-white"><svg aria-hidden="true" viewBox="0 0 24 24" class="h-5 w-5" fill="currentColor"><path d="m13.2 2-8 11h5.6L9.9 22l8.9-12h-5.9L13.2 2Z"/></svg></span>
                <span><strong class="block text-sm">Pesan dalam hitungan menit</strong><span class="mt-1 block text-xs text-muted">DP QRIS · Konfirmasi jadwal</span></span>
            </div>
        </div>
    </section>

    <section class="grid gap-8 py-16 md:grid-cols-[0.8fr_1.2fr] md:items-start">
        <div>
            <p class="text-xs font-semibold uppercase tracking-[0.16em] text-teal">Tiga langkah, langsung berangkat</p>
            <h2 class="mt-2 max-w-sm text-3xl font-bold leading-tight">Pesan dulu dari rumah. Ambil motor saat waktunya.</h2>
        </div>
        <ol class="grid gap-3 sm:grid-cols-3">
            @foreach ([['01', 'Pilih motor', 'Cek harga dan kalendernya. Pilih jam yang cocok.'], ['02', 'Lengkapi data', 'Isi jadwal, unggah KTP dan SIM C, lalu bayar DP via QRIS.'], ['03', 'Ambil kunci', 'Datang sesuai jadwal. Sisa pembayaran diselesaikan saat serah terima.']] as [$number, $title, $description])
                <li class="border-t-2 border-amber pt-4">
                    <span class="inline-flex h-9 w-9 items-center justify-center rounded-full bg-[#fff0cf] font-display text-sm font-bold text-[#8a5700]">{{ $number }}</span>
                    <h3 class="mt-3 text-lg font-semibold">{{ $title }}</h3>
                    <p class="mt-1.5 text-sm leading-6 text-muted">{{ $description }}</p>
                </li>
            @endforeach
        </ol>
    </section>

    <section class="grid gap-7 rounded-[1.75rem] border border-line bg-white p-5 shadow-sm sm:p-8 lg:grid-cols-[0.75fr_1.25fr]">
        <div class="flex flex-col justify-center gap-6">
            <div>
                <p class="text-sm font-semibold uppercase tracking-[0.16em] text-teal">Pilih sesuai perjalanan</p>
                <h2 class="mt-3 text-4xl font-bold leading-tight lg:text-5xl">Armada yang ada sekarang</h2>
                <p class="mt-4 max-w-md text-lg leading-8 text-muted">Buat keliling kota, antar jemput, atau perjalanan agak jauh. Cek detail tiap motor untuk lihat perlengkapan dan jadwal kosongnya.</p>
            </div>
            <a href="{{ route('bikes.index') }}" class="link inline-flex items-center gap-2 text-lg font-semibold">Buka katalog <span aria-hidden="true">→</span></a>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            @forelse ($featuredBikes as $bike)
                <a href="{{ route('bikes.show', $bike) }}" class="group overflow-hidden rounded-2xl border border-line bg-white transition duration-300 hover:-translate-y-1 hover:border-amber hover:shadow-lg">
                    <div class="h-44 overflow-hidden bg-[#fff3d8]">
                        @if ($bike->hasPhoto())
                            <img src="{{ $bike->photoUrl() }}" alt="{{ $bike->name }}" loading="lazy" class="h-full w-full object-cover transition duration-300 group-hover:scale-[1.03]">
                        @else
                            <div class="flex h-full items-center justify-center text-teal"><x-bike-icon :category="$bike->category" :size="76" /></div>
                        @endif
                    </div>
                    <div class="flex items-end justify-between gap-2 p-4">
                        <div><h3 class="font-semibold">{{ $bike->name }}</h3><p class="mt-0.5 text-xs text-muted">{{ $bike->brand }} · {{ ucfirst($bike->category) }}</p></div>
                        <p class="whitespace-nowrap text-sm font-semibold">{{ \App\Support\Format::rupiah($bike->daily_rate) }}<span class="text-xs font-normal text-muted">/hari</span></p>
                    </div>
                </a>
            @empty
                <p class="rounded-xl border border-dashed border-line p-6 text-sm text-muted sm:col-span-2">Armada sedang diperbarui. Coba cek katalog lagi sebentar.</p>
            @endforelse
        </div>
    </section>

    <section class="grid gap-8 py-14 lg:grid-cols-[1fr_0.8fr]">
        <div>
            <p class="text-xs font-semibold uppercase tracking-[0.16em] text-teal">Biar sama-sama enak</p>
            <h2 class="mt-2 text-4xl font-bold leading-tight">Sebelum kunci dibawa, perhatikan ketentuan berikut</h2>
            <div class="mt-5 rounded-xl border border-line bg-surface p-6 sm:p-8">
                <x-rental-policy class="!text-base leading-7 space-y-3" />
            </div>
        </div>

        <aside class="flex flex-col justify-center gap-8 rounded-2xl bg-[#e2efe9] p-6 sm:p-8">
            <div>
                <p class="text-sm font-semibold uppercase tracking-[0.16em] text-teal">Mampir langsung</p>
                <h2 class="mt-3 text-3xl font-bold leading-tight">Ada yang mau ditanyakan?</h2>
                <p class="mt-3 text-lg leading-8 text-muted">Hubungi kami untuk tanya jadwal, titik pengambilan, atau rekomendasi motor buat rute kamu.</p>
            </div>
            <div class="space-y-5 border-t border-teal/20 pt-6 text-base">
                <p><span class="block text-sm font-semibold uppercase tracking-wider text-muted">Kontak <span class="normal-case tracking-normal text-orange-700"></span></span><span class="mt-1.5 block text-lg font-semibold">0812-3456-7890</span></p>
                <p><span class="block text-sm font-semibold uppercase tracking-wider text-muted">Jam layanan</span><span class="mt-1.5 block text-lg font-semibold">{{ config('rental.operating_hours') ?: sprintf('Serah terima motor %02d.00–%02d.00 WIB', config('rental.open_hour'), config('rental.close_hour')) }}</span></p>
                <p><span class="block text-sm font-semibold uppercase tracking-wider text-muted">Alamat</span><span class="mt-1.5 block text-lg font-semibold leading-7">{{ config('rental.address') ?: (config('rental.whatsapp') ? 'Tanyakan titik pengambilan lewat WhatsApp.' : 'Jl. Pemuda Kelompok C No. A2, Airlangga, Kec. Mulyorejo, Kota Surabaya, Jawa Timur 60115') }}</span></p>
                @if (config('rental.whatsapp'))
                    <a href="https://wa.me/{{ config('rental.whatsapp') }}?text={{ rawurlencode('Halo Mitra Jalan, saya mau tanya soal sewa motor.') }}" class="btn inline-flex items-center gap-2 bg-teal text-white hover:bg-teal/90" target="_blank" rel="noopener">
                        <svg aria-hidden="true" viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M20 11.5a8.4 8.4 0 0 1-12.4 7.4L4 20l1.2-3.4A8.4 8.4 0 1 1 20 11.5Z"/><path stroke-linecap="round" stroke-linejoin="round" d="M8.7 8.3c.2-.5.4-.5.7-.5h.4c.2 0 .4.1.5.4l.6 1.5c.1.2 0 .4-.1.6l-.5.6c-.2.2-.1.4 0 .6.5.8 1.2 1.5 2.1 1.9.2.1.4.1.6-.1l.7-.8c.2-.2.4-.2.6-.1l1.4.7c.2.1.3.3.3.5 0 .4-.2 1.1-.7 1.4-.4.3-1 .5-1.7.4-1-.1-2.3-.7-3.5-1.8-1-.9-1.7-2.1-1.9-3-.2-.9 0-1.7.5-2.3Z"/></svg>
                        Chat WhatsApp
                    </a>
                @endif
            </div>
        </aside>
    </section>
@endsection
