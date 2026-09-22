<section>
    <header>
        <h2 class="section-title mt-0">Hapus akun</h2>
        <p class="hint">Setelah dihapus, seluruh data akunmu hilang permanen. Unduh dulu data yang ingin kamu simpan.</p>
    </header>

    <div class="mt-6">
        <x-danger-button
            x-data=""
            x-on:click.prevent="$dispatch('open-modal', 'confirm-user-deletion')"
        >Hapus akun</x-danger-button>
    </div>

    <x-modal name="confirm-user-deletion" :show="$errors->userDeletion->isNotEmpty()" focusable>
        <form method="post" action="{{ route('profile.destroy') }}" class="p-6">
            @csrf
            @method('delete')

            <h2 class="section-title mt-0">Yakin ingin menghapus akun?</h2>
            <p class="hint">Akun dan seluruh datanya akan dihapus permanen. Masukkan password untuk mengonfirmasi.</p>

            <div class="mt-5">
                <x-input-label for="delete_password" value="Password" class="sr-only" />
                <x-text-input id="delete_password" name="password" type="password" placeholder="Password" />
                <x-input-error :messages="$errors->userDeletion->get('password')" class="mt-1.5" />
            </div>

            <div class="mt-6 flex justify-end gap-2">
                <x-secondary-button x-on:click="$dispatch('close')">Batal</x-secondary-button>
                <x-danger-button>Hapus akun</x-danger-button>
            </div>
        </form>
    </x-modal>
</section>