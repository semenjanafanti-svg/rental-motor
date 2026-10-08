@php
    $user = auth()->user();
@endphp

<header class="site-header">
    <div class="topbar">
        <a href="{{ route('home') }}" class="brand">
            <img src="{{ asset('images/logo.png') }}" alt="Logo {{ config('rental.business_name') }}" class="brand-logo">
            {{ config('rental.business_name') }}
        </a>

        <div class="ml-auto flex items-center gap-1">
            @auth
                @if ($user->isStaff())
                    <a href="{{ url('/admin') }}" class="topbar-link">Panel Admin</a>
                @else
                    <a href="{{ route('rentals.index') }}" class="topbar-link">Pesanan Saya</a>
                @endif
                <a href="{{ route('profile.edit') }}" class="topbar-link" title="Profil">{{ $user->name }}</a>
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
