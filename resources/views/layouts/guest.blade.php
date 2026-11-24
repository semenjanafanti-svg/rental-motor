<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('rental.business_name') }}</title>
    @include('layouts.partials.assets')
</head>
<body>
    <header class="site-header">
        <div class="topbar">
            <a href="{{ route('home') }}" class="brand">
                <img src="{{ asset('images/logo.png') }}" alt="Logo {{ config('rental.business_name') }}" width="36" height="36" class="brand-logo">
                {{ config('rental.business_name') }}
            </a>
            <a href="{{ route('bikes.index') }}" class="topbar-link ml-auto">Lihat katalog</a>
        </div>
    </header>

    <main class="mx-auto w-full max-w-md flex-1 px-4 py-10 motion-safe:animate-fade-up">
        <div class="card p-6 sm:p-8">
            {{ $slot }}
        </div>
    </main>
</body>
</html>
