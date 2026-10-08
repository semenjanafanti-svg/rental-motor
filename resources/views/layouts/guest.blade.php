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
    <div class="topbar">
        <a href="{{ route('home') }}" class="brand">
            <img src="{{ asset('images/logo.png') }}" alt="Logo {{ config('rental.business_name') }}" width="40" height="40" class="brand-logo" style="display:block;width:40px;height:40px;max-width:40px;max-height:40px;object-fit:contain">
            {{ config('rental.business_name') }}
        </a>
    </div>

    <main class="mx-auto w-full max-w-md px-4 py-10 motion-safe:animate-fade-up">
        <div class="card p-6 sm:p-7">
            {{ $slot }}
        </div>
    </main>
</body>
</html>
