@php
    $user = auth()->user();
@endphp

<header class="site-header">
    <div class="topbar">
        <a href="{{ route('home') }}" class="brand">
            <span class="brand-plate">MJ</span> {{ config('rental.business_name') }}
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
                <a href="{{ route('register') }}" class="btn btn-amber btn-sm ml-1">Daftar</a>
            @endauth
        </div>
    </div>
</header>
