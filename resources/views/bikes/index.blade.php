@extends('layouts.public')

@section('title', 'Katalog Motor')

@php
    $categories = ['' => 'Semua', 'matic' => 'Matic', 'manual' => 'Manual', 'sport' => 'Sport'];
    $activeCategory = request('category', '');
    $availableOn = request('available_on');
    $availableLabel = $availableOn ? \Carbon\Carbon::parse($availableOn)->locale('id')->translatedFormat('d M Y') : null;
@endphp

@section('content')
    <div class="mb-6 flex flex-wrap items-end justify-between gap-3">
        <div>
            <p class="mb-1 text-xs font-bold uppercase tracking-[0.18em] text-[#bf6810]">Cari teman perjalananmu</p>
            <h1 class="font-display text-4xl font-bold leading-tight tracking-tight text-ink sm:text-5xl">Katalog Motor</h1>
            <p class="mt-2 text-sm text-muted sm:text-base">Pilih unit yang pas, cek jadwalnya, lalu berangkat.</p>
        </div>
        <p class="rounded-full bg-[#fff2d0] px-3.5 py-2 text-sm font-semibold text-[#71420c]">{{ $bikes->total() }} unit ditemukan</p>
    </div>

    <nav class="mb-5 flex gap-2 overflow-x-auto pb-1" aria-label="Filter kategori motor">
        @foreach ($categories as $value => $label)
            <a href="{{ request()->fullUrlWithQuery(['category' => $value ?: null, 'page' => null]) }}"
               class="shrink-0 rounded-full border px-5 py-2.5 text-sm font-semibold transition {{ $activeCategory === $value ? 'border-[#f2a20b] bg-gradient-to-r from-[#ffc928] to-[#ff941f] text-[#3b2108] shadow-md shadow-orange-100' : 'border-line bg-white text-muted hover:border-amber hover:bg-[#fffaf0] hover:text-ink' }}"
               @if ($activeCategory === $value) aria-current="page" @endif>{{ $label }}</a>
        @endforeach
    </nav>

    <form method="GET" action="{{ route('bikes.index') }}"
          class="mb-6 grid items-end gap-4 rounded-2xl border border-line bg-white p-4 shadow-sm sm:grid-cols-2 lg:grid-cols-[1fr_1fr_1fr_auto]">
        @if ($activeCategory)
            <input type="hidden" name="category" value="{{ $activeCategory }}">
        @endif

        <div>
            <label for="available_on" class="label">Tersedia pada tanggal</label>
            <input id="available_on" name="available_on" type="date" min="{{ now()->toDateString() }}"
                   value="{{ $availableOn }}" class="input">
            @error('available_on')<p class="field-error">{{ $message }}</p>@enderror
        </div>

        <div>
            <label for="max_price" class="label">Tarif harian maks. (Rp)</label>
            <input id="max_price" name="max_price" type="number" min="0" step="5000"
                   value="{{ request('max_price') }}" placeholder="Contoh: 100000" class="input">
        </div>

        <div>
            <label for="sort" class="label">Urutkan</label>
            <select id="sort" name="sort" class="input select">
                <option value="">Nama</option>
                <option value="price_asc" @selected(request('sort') === 'price_asc')>Termurah</option>
                <option value="price_desc" @selected(request('sort') === 'price_desc')>Termahal</option>
            </select>
        </div>

        <div class="flex gap-2">
            <button type="submit" class="btn btn-sm">Terapkan</button>
            <a href="{{ route('bikes.index') }}" class="btn btn-outline btn-sm">Reset</a>
        </div>
    </form>

    @if ($availableLabel)
        <p class="hint mb-4">Menampilkan motor yang bebas pada {{ $availableLabel }}.</p>
    @endif

    @if ($bikes->isEmpty())
        <div class="card">
            <div class="empty">
                <p class="font-semibold text-ink">Tidak ada motor yang cocok.</p>
                <p class="mt-1 text-sm">Coba ganti tanggal, kategori, atau batas tarif.</p>
                <a href="{{ route('bikes.index') }}" class="link mt-3 inline-block text-sm">Hapus semua filter</a>
            </div>
        </div>
    @else
        <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($bikes as $bike)
                <a href="{{ route('bikes.show', $bike) }}" class="group overflow-hidden rounded-2xl border border-line bg-white shadow-[0_8px_24px_-16px_rgba(28,29,26,.35)] transition duration-300 hover:-translate-y-1.5 hover:border-amber/60 hover:shadow-[0_20px_38px_-18px_rgba(163,93,17,.28)] motion-reduce:transform-none motion-reduce:transition-none">
                    <div class="relative h-52 overflow-hidden bg-gradient-to-br from-[#d9f2e9] via-[#f8f0d9] to-[#ffe1c5]">
                        @if ($bike->hasPhoto())
                            <img src="{{ $bike->photoUrl() }}" alt="{{ $bike->name }}" loading="lazy" decoding="async" class="h-full w-full object-cover transition duration-500 group-hover:scale-105">
                        @else
                            <div class="flex h-full items-center justify-center text-teal transition duration-500 group-hover:scale-105"><x-bike-icon :category="$bike->category" :size="108" /></div>
                        @endif
                        <div class="absolute inset-x-0 bottom-0 h-24 bg-gradient-to-t from-black/35 to-transparent"></div>
                        <span class="absolute left-4 top-4 rounded-full border border-white/70 bg-white/90 px-3 py-1 text-xs font-bold capitalize text-ink shadow-sm">{{ $bike->category }}</span>
                        @if ($availableLabel)
                            <span class="absolute bottom-4 right-4 rounded-full bg-emerald-100/95 px-3 py-1 text-xs font-bold text-emerald-800">Tersedia · {{ $availableLabel }}</span>
                        @elseif ($bike->is_actively_rented)
                            <span class="absolute bottom-4 right-4 rounded-full bg-rose-100/95 px-3 py-1 text-xs font-bold text-rose-800">Sedang disewa</span>
                        @else
                            <span class="absolute bottom-4 right-4 rounded-full bg-white/90 px-3 py-1 text-xs font-semibold text-ink">Siap disewa</span>
                        @endif
                    </div>

                    <div class="p-5">
                        <h2 class="font-display text-2xl font-bold leading-tight text-ink">{{ $bike->name }}</h2>
                        <p class="mt-0.5 text-[12.5px] text-muted">
                            {{ $bike->brand }}@if ($bike->color) · {{ $bike->color }} @endif
                        </p>

                        <div class="mt-3 flex flex-wrap gap-2">
                            @if ($bike->cc)
                                <span class="inline-flex items-center gap-1.5 rounded-lg bg-[#f4f5f2] px-2.5 py-1.5 text-xs font-semibold text-muted"><svg aria-hidden="true" viewBox="0 0 20 20" class="h-4 w-4 text-teal" fill="none" stroke="currentColor" stroke-width="1.6"><path stroke-linecap="round" stroke-linejoin="round" d="M5 6h10M4 10h12M6 14h8"/></svg>{{ $bike->cc }} cc</span>
                            @endif
                            @if ($bike->year)
                                <span class="rounded-lg bg-[#fff4d9] px-2.5 py-1.5 text-xs font-semibold text-[#8a5700]">Tahun {{ $bike->year }}</span>
                            @endif
                            <span class="inline-flex items-center gap-1.5 rounded-lg bg-[#f4f5f2] px-2.5 py-1.5 text-xs font-semibold text-muted"><svg aria-hidden="true" viewBox="0 0 20 20" class="h-4 w-4 text-[#16865e]" fill="none" stroke="currentColor" stroke-width="1.6"><path stroke-linecap="round" stroke-linejoin="round" d="M7 17V4h6v13M7 7h6m2-1 2 2v5h-2"/><path stroke-linecap="round" stroke-linejoin="round" d="M6 17h8"/></svg>BBM penuh</span>
                        </div>
                        <p class="mt-2.5 text-[14.5px] font-semibold">
                            {{ \App\Support\Format::rupiah($bike->daily_rate) }}
                            <span class="text-[12.5px] font-normal text-muted">/ 24 jam</span>
                        </p>

                        @if ($availableLabel)
                            <span class="badge badge-teal badge-dot mt-2">Bebas {{ $availableLabel }}</span>
                        @endif
                    </div>
                </a>
            @endforeach
        </div>

        <div class="mt-6">{{ $bikes->links() }}</div>
    @endif
@endsection
