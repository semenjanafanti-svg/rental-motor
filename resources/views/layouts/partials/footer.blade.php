<footer class="site-footer">
    <div class="mx-auto flex w-full max-w-6xl flex-col gap-4 px-4 py-8 text-sm sm:flex-row sm:items-center sm:justify-between sm:px-6">
        <div>
            <p class="font-display text-base font-semibold text-ink">{{ config('rental.business_name') }}</p>
            <p class="mt-1">Sewa motor harian · DP {{ config('rental.dp_percent') }}% via QRIS</p>
        </div>
        <nav class="flex flex-wrap gap-x-5 gap-y-2" aria-label="Tautan footer">
            <a href="{{ route('bikes.index') }}" class="hover:text-ink">Katalog</a>
            @auth
                @unless (auth()->user()->isStaff())
                    <a href="{{ route('rentals.index') }}" class="hover:text-ink">Pesanan saya</a>
                @endunless
            @else
                <a href="{{ route('login') }}" class="hover:text-ink">Masuk</a>
            @endauth
            <span>&copy; {{ now()->year }}</span>
        </nav>
    </div>
</footer>
