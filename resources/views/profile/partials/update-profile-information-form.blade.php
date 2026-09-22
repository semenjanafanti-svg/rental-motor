<section>
    <header>
        <h2 class="section-title mt-0">Informasi profil</h2>
        <p class="hint">Perbarui nama dan alamat email akunmu.</p>
    </header>

    <form id="send-verification" method="post" action="{{ route('verification.send') }}">
        @csrf
    </form>

    <form method="post" action="{{ route('profile.update') }}" class="mt-6 space-y-4">
        @csrf
        @method('patch')

        <div>
            <x-input-label for="name" value="Nama" />
            <x-text-input id="name" name="name" type="text" :value="old('name', $user->name)" required autofocus autocomplete="name" />
            <x-input-error class="mt-1.5" :messages="$errors->get('name')" />
        </div>

        <div>
            <x-input-label for="email" value="Email" />
            <x-text-input id="email" name="email" type="email" :value="old('email', $user->email)" required autocomplete="username" />
            <x-input-error class="mt-1.5" :messages="$errors->get('email')" />

            @if ($user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && ! $user->hasVerifiedEmail())
                <div class="notice mt-3">
                    Email kamu belum diverifikasi.
                    <button form="send-verification" class="link">Kirim ulang email verifikasi</button>
                </div>

                @if (session('status') === 'verification-link-sent')
                    <p class="hint mt-2 text-moss">Tautan verifikasi baru sudah dikirim ke emailmu.</p>
                @endif
            @endif
        </div>

        <div class="flex items-center gap-4">
            <x-primary-button>Simpan perubahan</x-primary-button>

            @if (session('status') === 'profile-updated')
                <p x-data="{ show: true }" x-show="show" x-transition x-init="setTimeout(() => show = false, 2500)"
                   class="text-sm text-muted">Tersimpan.</p>
            @endif
        </div>
    </form>
</section>