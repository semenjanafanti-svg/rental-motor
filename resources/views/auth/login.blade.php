<x-guest-layout>
    <h1 class="page-title">Masuk</h1>
    <p class="page-sub">Masuk untuk memesan motor dan memantau pesananmu.</p>

    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form method="POST" action="{{ route('login') }}" class="space-y-4">
        @csrf

        <div>
            <x-input-label for="email" value="Email" />
            <x-text-input id="email" type="email" name="email" :value="old('email')" required autofocus autocomplete="username" />
            <x-input-error :messages="$errors->get('email')" class="mt-1.5" />
        </div>

        <div>
            <x-input-label for="password" value="Password" />
            <x-text-input id="password" type="password" name="password" required autocomplete="current-password" />
            <x-input-error :messages="$errors->get('password')" class="mt-1.5" />
        </div>

        <div class="flex items-center justify-between gap-3">
            <label for="remember_me" class="inline-flex items-center gap-2 text-sm text-muted">
                <input id="remember_me" type="checkbox" class="check" name="remember">
                Ingat saya
            </label>

            @if (Route::has('password.request'))
                <a class="link text-sm" href="{{ route('password.request') }}">Lupa password?</a>
            @endif
        </div>

        <button type="submit" class="btn btn-amber btn-block">Masuk</button>
    </form>

    <p class="mt-5 text-center text-sm text-muted">
        Belum punya akun?
        <a class="link" href="{{ route('register') }}">Daftar</a>
    </p>
</x-guest-layout>
