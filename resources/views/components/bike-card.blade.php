{{-- Kartu motor putih dengan tombol panah kuning (aksen). Dipakai di beranda dan katalog. --}}
@props(['bike', 'showStatus' => false])

<a href="{{ route('bikes.show', $bike) }}"
   {{ $attributes->merge(['class' => 'group flex flex-col rounded-3xl border border-line bg-surface p-2.5 text-ink shadow-[var(--shadow)] transition duration-300 hover:-translate-y-1 hover:shadow-lg active:scale-[.98] motion-reduce:transform-none motion-reduce:transition-none']) }}>
    <div class="relative h-48 overflow-hidden rounded-[1.25rem] bg-paper">
        @if ($bike->hasPhoto())
            <img src="{{ $bike->photoUrl() }}" alt="{{ $bike->name }}" loading="lazy" decoding="async"
                 class="h-full w-full object-cover transition duration-500 group-hover:scale-105 motion-reduce:transition-none">
        @else
            <x-bike-icon :category="$bike->category" :size="96" />
        @endif
        <span class="absolute left-3 top-3 rounded-full bg-white/85 px-3 py-1 text-xs font-semibold capitalize text-ink backdrop-blur">{{ $bike->category }}</span>
        @if ($showStatus)
            @if ($bike->is_actively_rented)
                <span class="badge badge-rust badge-dot absolute bottom-3 left-3">Sedang disewa</span>
            @else
                <span class="badge badge-dot absolute bottom-3 left-3 bg-white text-ok">Siap disewa</span>
            @endif
        @endif
    </div>

    <div class="flex flex-1 items-end justify-between gap-3 px-2.5 pb-2 pt-4">
        <div class="min-w-0">
            <p class="truncate text-xs text-muted">
                {{ $bike->brand }}@if ($bike->cc) · {{ $bike->cc }} cc @endif @if ($bike->color) · {{ $bike->color }} @endif
            </p>
            <h3 class="mt-0.5 truncate font-display text-lg font-semibold leading-snug">{{ $bike->name }}</h3>
            <p class="mt-1.5 text-sm font-semibold">
                {{ \App\Support\Format::rupiah($bike->daily_rate) }}<span class="font-normal text-muted"> / 24 jam</span>
            </p>
        </div>
        <span class="arrow-btn" aria-hidden="true">
            <svg viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2.2"><path stroke-linecap="round" stroke-linejoin="round" d="M7 17 17 7M8 7h9v9"/></svg>
        </span>
    </div>
</a>
