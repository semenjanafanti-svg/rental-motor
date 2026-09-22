<x-guest-layout>
    <h1 class="page-title">Verifikasi email</h1>
    <p class="page-sub">Kami sudah mengirim tautan verifikasi ke emailmu. Klik tautan itu untuk melanjutkan. Belum menerima emailnya? Kirim ulang di bawah.</p>

    @if (session('status') === 'verification-link-sent')
        <div class="notice notice-teal mb-4" role="status">Tautan verifikasi baru sudah dikirim ke emailmu.</div>
    @endif

    <div class="flex flex-wrap items-center justify-between gap-3">
        <form method="POST" action="{{ route('verification.send') }}">
            @csrf
            <button type="submit" class="btn btn-amber">Kirim ulang email</button>
        </form>

        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="link text-sm">Keluar</button>
        </form>
    </div>
</x-guest-layout>