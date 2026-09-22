<x-guest-layout>
    <h1 class="page-title">Lupa password</h1>
    <p class="page-sub">Masukkan email akunmu. Kami kirim tautan untuk membuat password baru.</p>

    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form method="POST" action="{{ route('password.email') }}" class="space-y-4">
        @csrf

        <div>
            <x-input-label for="email" value="Email" />
            <x-text-input id="email" type="email" name="email" :value="old('email')" required autofocus />
            <x-input-error :messages="$errors->get('email')" class="mt-1.5" />
        </div>

        <button type="submit" class="btn btn-amber btn-block">Kirim tautan reset</button>
    </form>

    <p class="mt-5 text-center text-sm text-muted">
        <a class="link" href="{{ route('login') }}">Kembali ke halaman masuk</a>
    </p>
</x-guest-layout>