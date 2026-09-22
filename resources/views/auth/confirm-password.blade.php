<x-guest-layout>
    <h1 class="page-title">Konfirmasi password</h1>
    <p class="page-sub">Ini area yang dilindungi. Masukkan password kamu sebelum melanjutkan.</p>

    <form method="POST" action="{{ route('password.confirm') }}" class="space-y-4">
        @csrf

        <div>
            <x-input-label for="password" value="Password" />
            <x-text-input id="password" type="password" name="password" required autofocus autocomplete="current-password" />
            <x-input-error :messages="$errors->get('password')" class="mt-1.5" />
        </div>

        <button type="submit" class="btn btn-amber btn-block">Konfirmasi</button>
    </form>
</x-guest-layout>