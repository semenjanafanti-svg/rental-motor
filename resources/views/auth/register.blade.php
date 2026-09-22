<x-guest-layout>
    <h1 class="page-title">Buat akun</h1>
    <p class="page-sub">Akun dibutuhkan untuk memesan motor dan menerima pengingat lewat WhatsApp.</p>

    <form method="POST" action="{{ route('register') }}" class="space-y-4">
        @csrf

        <div>
            <x-input-label for="name" value="Nama" />
            <x-text-input id="name" type="text" name="name" :value="old('name')" required autofocus autocomplete="name" />
            <x-input-error :messages="$errors->get('name')" class="mt-1.5" />
        </div>

        <div>
            <x-input-label for="email" value="Email" />
            <x-text-input id="email" type="email" name="email" :value="old('email')" required autocomplete="username" />
            <x-input-error :messages="$errors->get('email')" class="mt-1.5" />
        </div>

        <div>
            <x-input-label for="phone_number" value="Nomor WhatsApp" />
            <x-text-input id="phone_number" type="tel" name="phone_number" :value="old('phone_number')"
                          required autocomplete="tel" placeholder="081234567890" />
            <p class="hint">Dipakai admin untuk mengirim pengingat pengambilan dan pengembalian.</p>
            <x-input-error :messages="$errors->get('phone_number')" class="mt-1.5" />
        </div>

        <div>
            <x-input-label for="password" value="Password" />
            <x-text-input id="password" type="password" name="password" required autocomplete="new-password" />
            <x-input-error :messages="$errors->get('password')" class="mt-1.5" />
        </div>

        <div>
            <x-input-label for="password_confirmation" value="Ulangi password" />
            <x-text-input id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password" />
            <x-input-error :messages="$errors->get('password_confirmation')" class="mt-1.5" />
        </div>

        <button type="submit" class="btn btn-amber btn-block">Daftar</button>
    </form>

    <p class="mt-5 text-center text-sm text-muted">
        Sudah punya akun?
        <a class="link" href="{{ route('login') }}">Masuk</a>
    </p>
</x-guest-layout>
