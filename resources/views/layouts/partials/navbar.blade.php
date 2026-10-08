@php
    $user = auth()->user();
@endphp

<header class="site-header">
    <div class="topbar">
        <a href="{{ auth()->check() && ! $user->isStaff() ? route('bikes.index') : route('home') }}" class="brand">
            <img src="{{ asset('images/logo.png') }}" alt="Logo {{ config('rental.business_name') }}" width="40" height="40" class="brand-logo" style="display:block;width:40px;height:40px;max-width:40px;max-height:40px;object-fit:contain">
            {{ config('rental.business_name') }}
        </a>

        <div class="ml-auto flex items-center gap-1">
            @auth
                @if ($user->isStaff())
                    <a href="{{ url('/admin') }}" class="topbar-link">Panel Admin</a>
                @else
                    <a href="{{ route('rentals.index') }}" class="topbar-link">Pesanan Saya</a>
                @endif
                <a href="{{ route('profile.edit') }}" class="topbar-link inline-flex items-center" title="Profil" aria-label="Profil">
                    <svg aria-hidden="true" viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8">
                        <circle cx="12" cy="8" r="3.5" />
                        <path stroke-linecap="round" d="M4.5 20c.8-3.5 3.5-5.5 7.5-5.5s6.7 2 7.5 5.5" />
                    </svg>
                </a>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="topbar-link">Keluar</button>
                </form>
            @else
                <a href="{{ route('login') }}" class="topbar-link">Masuk</a>
            <a href="{{ route('register') }}" class="btn btn-amber ml-1 px-4 py-2.5 text-sm font-bold sm:px-5 sm:text-base">Daftar</a>
            @endauth
        </div>
    </div>
</header>
