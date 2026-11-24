@php
    $user = auth()->user();
@endphp

<header class="site-header">
    <div class="topbar">
        <a href="{{ auth()->check() && ! $user->isStaff() ? route('bikes.index') : route('home') }}" class="brand">
            <img src="{{ asset('images/logo.png') }}" alt="Logo {{ config('rental.business_name') }}" width="36" height="36" class="brand-logo">
            {{ config('rental.business_name') }}
        </a>

        <nav class="ml-auto flex items-center gap-1" aria-label="Menu utama">
            <a href="{{ route('bikes.index') }}" @class(['topbar-link hidden sm:inline-block', 'topbar-link-active' => request()->routeIs('bikes.*', 'bookings.*')])>Katalog</a>
            @auth
                @if ($user->isStaff())
                    <a href="{{ url('/admin') }}" class="topbar-link">Panel Admin</a>
                @else
                    <a href="{{ route('customer.dashboard') }}" @class(['topbar-link', 'topbar-link-active' => request()->routeIs('customer.dashboard')])>Dashboard</a>
                    <a href="{{ route('rentals.index') }}" @class(['topbar-link', 'topbar-link-active' => request()->routeIs('rentals.*')])>Pesanan</a>
                @endif
                <a href="{{ route('profile.edit') }}" @class(['topbar-link inline-flex items-center', 'topbar-link-active' => request()->routeIs('profile.*')]) title="Profil" aria-label="Profil">
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
                <a href="{{ route('register') }}" class="btn btn-volt btn-sm ml-1">Daftar</a>
            @endauth
        </nav>
    </div>
</header>
