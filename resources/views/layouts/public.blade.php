<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Rental Motor') - {{ config('rental.business_name') }}</title>
    @include('layouts.partials.assets')
</head>
<body>
    @include('layouts.partials.navbar')

    <main class="page motion-safe:animate-fade-up">
        @if (session('status'))
            <div class="notice notice-teal mb-5" role="status">{{ session('status') }}</div>
        @endif

        @if ($errors->has('booking'))
            <div class="notice notice-rust mb-5" role="alert">{{ $errors->first('booking') }}</div>
        @endif

        @yield('content')
    </main>
</body>
</html>
