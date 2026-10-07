@extends('layouts.public')

@section('title', 'Pesanan Saya')

@section('content')
    @php
        $noShowRefreshAt = $rentals->getCollection()
            ->filter(fn ($rental) => $rental->status === 'approved')
            ->map(fn ($rental) => $rental->start_time->copy()->addMinutes((int) config('rental.no_show_tolerance_minutes')))
            ->filter(fn ($deadline) => $deadline->isFuture())
            ->sortBy(fn ($deadline) => $deadline->timestamp)
            ->first();
    @endphp

    <div class="mx-auto max-w-6xl">
        <header class="mb-7 flex flex-col justify-between gap-4 border-b border-line pb-6 sm:flex-row sm:items-end">
            <div>
                <p class="mb-2 text-xs font-bold uppercase tracking-[.18em] text-teal">Ruang perjalananmu</p>
                <h1 class="page-title mb-1">Pesanan Saya</h1>
                <p class="page-sub mb-0">Cek jadwal, status, dan pembayaran sewa motormu di sini.</p>
            </div>
            <a href="{{ route('bikes.index') }}" class="btn btn-amber inline-flex items-center justify-center gap-2 self-start sm:self-auto">
                <svg aria-hidden="true" viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 5v14m-7-7h14"/></svg>
                Cari motor
            </a>
        </header>

        @if ($rentals->isEmpty())
            <section class="overflow-hidden rounded-3xl border border-line bg-white shadow-sm">
                <div class="grid items-center gap-6 p-6 sm:grid-cols-[1fr_auto] sm:p-10">
                    <div>
                        <span class="inline-flex rounded-full bg-amber-50 px-3 py-1 text-xs font-bold text-amber-800">Belum ada booking</span>
                        <h2 class="mt-4 font-display text-3xl font-bold text-ink">Siap jalan ke mana?</h2>
                        <p class="mt-2 max-w-lg text-sm leading-6 text-muted">Pesanan motormu akan muncul di sini. Pilih unit yang pas, tentukan jadwal, lalu lanjutkan booking dari katalog.</p>
                        <a href="{{ route('bikes.index') }}" class="btn btn-amber mt-5 inline-flex">Lihat katalog motor <span aria-hidden="true" class="ml-2">→</span></a>
                    </div>
                    <div class="mx-auto flex h-28 w-28 items-center justify-center rounded-[2rem] bg-gradient-to-br from-amber-100 via-orange-100 to-teal-100 text-teal sm:mx-0 sm:h-36 sm:w-36">
                        <svg aria-hidden="true" viewBox="0 0 24 24" class="h-16 w-16" fill="none" stroke="currentColor" stroke-width="1.35"><circle cx="6" cy="17" r="3"/><circle cx="18" cy="17" r="3"/><path stroke-linecap="round" stroke-linejoin="round" d="M9 17h4l-3-7h3l3 7M6 14l3-4h3m2 0h2"/></svg>
                    </div>
                </div>
            </section>
        @else
            <div class="mb-4 flex items-center justify-between">
                <h2 class="font-display text-xl font-bold">Riwayat sewa</h2>
                <span class="rounded-full border border-line bg-white px-3 py-1 text-xs font-semibold text-muted">{{ $rentals->total() }} pesanan</span>
            </div>

            <div class="grid gap-4">
                @foreach ($rentals as $rental)
                    <article class="group overflow-hidden rounded-2xl border border-line bg-white shadow-[0_8px_24px_-20px_rgba(28,29,26,.5)] transition duration-200 hover:-translate-y-0.5 hover:shadow-[0_16px_32px_-22px_rgba(28,29,26,.38)]">
                        <div class="grid md:grid-cols-[minmax(0,1fr)_auto]">
                            <div class="min-w-0 p-5 sm:p-6">
                                <div class="mb-4 flex flex-wrap items-center gap-2">
                                    <x-status-badge :status="$rental->status" />
                                    @if ($rental->payment_status !== 'unpaid')
                                        <x-status-badge :status="$rental->payment_status" type="payment" />
                                    @endif
                                    @if ($rental->isOverdue())
                                        <span class="badge badge-rust badge-dot">Terlambat</span>
                                    @endif
                                </div>

                                <div class="flex flex-wrap items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <p class="text-xs font-semibold uppercase tracking-wide text-muted">{{ $rental->booking_code }}</p>
                                        <h3 class="mt-1 font-display text-2xl font-bold leading-tight text-ink">{{ $rental->bike->name }}</h3>
                                    </div>
                                    <p class="font-display text-xl font-bold text-ink">{{ \App\Support\Format::rupiah($rental->total_price) }}<span class="ml-1 font-sans text-xs font-medium text-muted">total</span></p>
                                </div>

                                <div class="mt-5 grid gap-3 border-t border-line pt-4 sm:grid-cols-2">
                                    <div class="flex items-start gap-3">
                                        <span class="mt-0.5 flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-amber-50 text-amber-700">
                                            <svg aria-hidden="true" viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.7"><rect x="3" y="5" width="18" height="16" rx="2"/><path stroke-linecap="round" d="M16 3v4M8 3v4M3 10h18"/></svg>
                                        </span>
                                        <div>
                                            <p class="text-[11px] font-bold uppercase tracking-wide text-muted">Mulai</p>
                                            <p class="mt-0.5 text-sm font-semibold text-ink">{{ $rental->start_time->locale('id')->translatedFormat('D, d M Y · H:i') }}</p>
                                        </div>
                                    </div>
                                    <div class="flex items-start gap-3">
                                        <span class="mt-0.5 flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-teal-50 text-teal">
                                            <svg aria-hidden="true" viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.7"><circle cx="12" cy="12" r="9"/><path stroke-linecap="round" stroke-linejoin="round" d="M12 7v5l3 2"/></svg>
                                        </span>
                                        <div>
                                            <p class="text-[11px] font-bold uppercase tracking-wide text-muted">Batas kembali</p>
                                            <p class="mt-0.5 text-sm font-semibold text-ink">{{ $rental->end_time->locale('id')->translatedFormat('D, d M Y · H:i') }}</p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="flex flex-row items-center justify-between gap-3 border-t border-line bg-[#fffdf8] p-4 md:w-52 md:flex-col md:items-stretch md:justify-center md:border-l md:border-t-0 md:p-5">
                                <a href="{{ route('rentals.show', $rental) }}" class="btn btn-amber inline-flex items-center justify-center gap-2">Detail pesanan <span aria-hidden="true">→</span></a>
                                @if ($rental->status === 'completed')
                                    <a href="{{ route('rentals.receipt', $rental) }}" class="btn btn-outline inline-flex items-center justify-center gap-2">
                                        <svg aria-hidden="true" viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3v12m0 0 4-4m-4 4-4-4M5 17v3h14v-3"/></svg>
                                        Unduh nota
                                    </a>
                                @endif
                            </div>
                        </div>
                    </article>
                @endforeach
            </div>

            @if ($rentals->hasPages())
                <div class="mt-6">{{ $rentals->links() }}</div>
            @endif
        @endif
    </div>

    @if ($noShowRefreshAt)
        <script>
            window.setTimeout(() => window.location.reload(), Math.max(1000, {{ ($noShowRefreshAt->timestamp - now()->timestamp) * 1000 }}));
        </script>
    @endif
@endsection
