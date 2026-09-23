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
        <a href="{{ route('bikes.index') }}" class="brand">
            <span class="brand-plate">MJ</span> {{ config('rental.business_name') }}
        </a>
    </div>

    <main class="mx-auto w-full max-w-md px-4 py-10 motion-safe:animate-fade-up">
        <div class="card p-6 sm:p-7">
            {{ $slot }}
        </div>
    </main>
</body>
</html>
