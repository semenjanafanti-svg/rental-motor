<x-app-layout>
    <x-slot name="header">
        <h1 class="page-title">Profil</h1>
        <p class="page-sub">Kelola data akun, password, dan penghapusan akun.</p>
    </x-slot>

    <div class="mx-auto max-w-3xl space-y-5">
        <div class="card p-4 sm:p-7">
            <div class="max-w-xl">
                @include('profile.partials.update-profile-information-form')
            </div>
        </div>

        <div class="card p-4 sm:p-7">
            <div class="max-w-xl">
                @include('profile.partials.update-password-form')
            </div>
        </div>

        <div class="card p-4 sm:p-7">
            <div class="max-w-xl">
                @include('profile.partials.delete-user-form')
            </div>
        </div>
    </div>
</x-app-layout>