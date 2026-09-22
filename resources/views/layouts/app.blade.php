<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'Rental Motor') }}</title>
    @include('layouts.partials.assets')
</head>
<body>
    @include('layouts.partials.navbar')

    <main class="page motion-safe:animate-fade-up">
        @isset($header)
            <div class="mb-5">{{ $header }}</div>
        @endisset

        {{ $slot }}
    </main>
</body>
</html>
