<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Rental Motor') - {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-50 text-gray-800 antialiased">
    <nav class="bg-white border-b border-gray-200">
        <div class="max-w-6xl mx-auto px-4 h-14 flex items-center justify-between">
            <a href="{{ route('bikes.index') }}" class="font-semibold text-lg">Rental Motor</a>

            <div class="flex items-center gap-4 text-sm">
                <a href="{{ route('bikes.index') }}" class="hover:text-indigo-600">Katalog</a>

                @auth
                    @if (auth()->user()->role === 'customer')
                        <a href="{{ route('rentals.index') }}" class="hover:text-indigo-600">Riwayat Sewa</a>
                    @endif
                    <span class="text-gray-500">{{ auth()->user()->name }}</span>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="text-red-600 hover:underline">Keluar</button>
                    </form>
                @else
                    <a href="{{ route('login') }}" class="hover:text-indigo-600">Masuk</a>
                    <a href="{{ route('register') }}" class="rounded-md bg-indigo-600 px-3 py-1.5 text-white hover:bg-indigo-700">Daftar</a>
                @endauth
            </div>
        </div>
    </nav>

    <main class="max-w-6xl mx-auto px-4 py-8">
        @if (session('status'))
            <div class="mb-6 rounded-md border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">
                {{ session('status') }}
            </div>
        @endif

        @if ($errors->has('booking'))
            <div class="mb-6 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                {{ $errors->first('booking') }}
            </div>
        @endif

        @yield('content')
    </main>
</body>
</html>
