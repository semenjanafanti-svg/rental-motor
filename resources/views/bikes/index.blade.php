@extends('layouts.public')

@section('title', 'Katalog Motor')

@section('content')
    <h1 class="text-2xl font-semibold mb-6">Katalog Motor</h1>

    <form method="GET" action="{{ route('bikes.index') }}"
          class="mb-6 grid items-end gap-4 rounded-lg border border-gray-200 bg-white p-4 sm:grid-cols-4">
        <div>
            <label for="category" class="mb-1 block text-sm font-medium">Kategori</label>
            <select id="category" name="category" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm">
                <option value="">Semua</option>
                @foreach (['matic' => 'Matic', 'manual' => 'Manual', 'sport' => 'Sport'] as $value => $label)
                    <option value="{{ $value }}" @selected(request('category') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label for="max_price" class="mb-1 block text-sm font-medium">Tarif harian maks. (Rp)</label>
            <input id="max_price" name="max_price" type="number" min="0" step="5000"
                   value="{{ request('max_price') }}" placeholder="Contoh: 100000"
                   class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm">
        </div>

        <div>
            <label for="sort" class="mb-1 block text-sm font-medium">Urutkan</label>
            <select id="sort" name="sort" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm">
                <option value="">Nama</option>
                <option value="price_asc" @selected(request('sort') === 'price_asc')>Termurah</option>
                <option value="price_desc" @selected(request('sort') === 'price_desc')>Termahal</option>
            </select>
        </div>

        <div class="flex gap-2">
            <button type="submit" class="rounded-md bg-indigo-600 px-4 py-2 text-sm text-white hover:bg-indigo-700">Terapkan</button>
            <a href="{{ route('bikes.index') }}" class="rounded-md border border-gray-300 px-4 py-2 text-sm hover:bg-gray-50">Reset</a>
        </div>
    </form>

    @if ($bikes->isEmpty())
        <div class="rounded-lg border border-gray-200 bg-white p-8 text-center text-gray-500">
            Tidak ada motor yang sesuai dengan filter.
        </div>
    @else
        <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($bikes as $bike)
                <a href="{{ route('bikes.show', $bike) }}"
                   class="block overflow-hidden rounded-lg border border-gray-200 bg-white transition hover:shadow-md">
                    @if ($bike->photo)
                        <img src="{{ asset('storage/' . $bike->photo) }}" alt="{{ $bike->name }}" class="h-40 w-full object-cover">
                    @else
                        <div class="flex h-40 items-center justify-center bg-gray-100 text-5xl">🏍️</div>
                    @endif

                    <div class="p-4">
                        <div class="text-xs uppercase tracking-wide text-gray-500">
                            {{ $bike->brand }} &middot; {{ ucfirst($bike->category) }}
                        </div>
                        <div class="font-semibold">{{ $bike->name }}</div>
                        <div class="mt-2 font-semibold text-indigo-600">
                            {{ \App\Support\Format::rupiah($bike->daily_rate) }}
                            <span class="text-xs font-normal text-gray-500">/ 24 jam</span>
                        </div>
                        <div class="text-xs text-gray-500">{{ \App\Support\Format::rupiah($bike->hourly_rate) }} / jam</div>
                    </div>
                </a>
            @endforeach
        </div>

        <div class="mt-6">{{ $bikes->links() }}</div>
    @endif
@endsection
