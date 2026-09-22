@extends('layouts.public')

@section('title', 'Katalog Motor')

@php
    $categories = ['' => 'Semua', 'matic' => 'Matic', 'manual' => 'Manual', 'sport' => 'Sport'];
    $activeCategory = request('category', '');
    $availableOn = request('available_on');
    $availableLabel = $availableOn ? \Carbon\Carbon::parse($availableOn)->locale('id')->translatedFormat('d M Y') : null;
@endphp

@section('content')
    <h1 class="page-title">Katalog Motor</h1>
    <p class="page-sub">Pilih motor, tentukan jadwal, lalu bayar DP lewat QRIS.</p>

    <form method="GET" action="{{ route('bikes.index') }}"
          class="card mb-4 grid items-end gap-4 p-4 sm:grid-cols-2 lg:grid-cols-[1fr_1fr_1fr_auto]">
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

    <div class="mb-5 flex flex-wrap gap-2" role="group" aria-label="Kategori motor">
        @foreach ($categories as $value => $label)
            <a href="{{ request()->fullUrlWithQuery(['category' => $value ?: null, 'page' => null]) }}"
               class="btn btn-sm {{ $activeCategory === $value ? '' : 'btn-outline' }}"
               @if ($activeCategory === $value) aria-current="true" @endif>{{ $label }}</a>
        @endforeach
    </div>

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
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($bikes as $bike)
                <a href="{{ route('bikes.show', $bike) }}" class="bike-card">
                    <div class="h-32">
                        @if ($bike->photo)
                            <img src="{{ asset('storage/' . $bike->photo) }}" alt="{{ $bike->name }}" class="h-full w-full object-cover">
                        @else
                            <x-bike-icon :category="$bike->category" />
                        @endif
                    </div>

                    <div class="p-4">
                        <h2 class="text-base font-semibold">{{ $bike->name }}</h2>
                        <p class="mt-0.5 text-[12.5px] text-muted">
                            {{ $bike->brand }} · {{ $bike->cc ? $bike->cc . 'cc · ' : '' }}{{ ucfirst($bike->category) }}
                        </p>
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
