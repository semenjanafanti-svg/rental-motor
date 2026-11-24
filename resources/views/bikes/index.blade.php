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
            <p class="eyebrow">Cari teman perjalananmu</p>
            <h1 class="page-title mt-1 sm:text-4xl">Katalog motor</h1>
            <p class="mt-1 text-sm text-muted sm:text-[15px]">Pilih unit yang pas, cek jadwalnya, lalu berangkat.</p>
        </div>
        <p class="badge badge-neutral px-3.5 py-2 text-sm">{{ $bikes->total() }} unit ditemukan</p>
    </div>

    <nav class="mb-4 flex gap-2 overflow-x-auto pb-1" aria-label="Filter kategori motor">
        @foreach ($categories as $value => $label)
            <a href="{{ request()->fullUrlWithQuery(['category' => $value ?: null, 'page' => null]) }}"
               @class(['chip', 'chip-active' => $activeCategory === $value])
               @if ($activeCategory === $value) aria-current="page" @endif>{{ $label }}</a>
        @endforeach
    </nav>

    <form method="GET" action="{{ route('bikes.index') }}"
          class="card mb-6 grid items-end gap-4 p-4 sm:grid-cols-2 lg:grid-cols-[1fr_1fr_1fr_auto]">
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
            <button type="submit" class="btn">Terapkan</button>
            <a href="{{ route('bikes.index') }}" class="btn btn-outline">Reset</a>
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
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($bikes as $bike)
                <x-bike-card :bike="$bike" :show-status="! $availableLabel" />
            @endforeach
        </div>

        <div class="mt-6">{{ $bikes->links() }}</div>
    @endif
@endsection
