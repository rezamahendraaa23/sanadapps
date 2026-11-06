<div>
    <x-ui.page-toolbar>
        <div class="w-full md:max-w-sm">
            <x-ui.input label="Cari nama atau email" name="search" type="search" wire:model.live.debounce.300ms="search" />
        </div>
        <x-ui.button icon="plus" wire:click="create">Tambah Pengguna</x-ui.button>
    </x-ui.page-toolbar>

    @if ($revealedPassword)
        <x-ui.banner tone="warning" class="mb-4">Sandi sementara: <strong class="font-tabular">{{ $revealedPassword }}</strong>. Catat sekarang dan berikan ke pemilik akun. Sandi ini hanya tampil sekali.</x-ui.banner>
    @endif

    <x-ui.card>
        @if ($users->isEmpty())
            <x-ui.empty-state icon="user-cog" message="Tidak ada pengguna yang cocok dengan pencarian ini." />
        @else
            <x-ui.table caption="Daftar pengguna staf">
                <thead><tr><x-ui.th>Nama</x-ui.th><x-ui.th>Email</x-ui.th><x-ui.th>Role</x-ui.th><x-ui.th>Status</x-ui.th><x-ui.th>Aksi</x-ui.th></tr></thead>
                <tbody>
                    @foreach ($users as $user)
                        <tr wire:key="user-{{ $user->uuid }}">
                            <x-ui.td label="Nama">{{ $user->name }}</x-ui.td>
                            <x-ui.td label="Email">{{ $user->email }}</x-ui.td>
                            <x-ui.td label="Role">{{ $user->roles->map(fn ($role) => $roleOptions[$role->name] ?? $role->name)->join(', ') }}</x-ui.td>
                            <x-ui.td label="Status">
                                @if ($user->is_active)
                                    <x-ui.status-badge status="active" />
                                @else
                                    <x-ui.status-badge status="blocked" />
                                @endif
                            </x-ui.td>
                            <x-ui.td label="Aksi">
                                <span class="inline-flex flex-wrap justify-end gap-1">
                                    <x-ui.button variant="tertiary" wire:click="edit('{{ $user->uuid }}')">Ubah<span class="sr-only"> {{ $user->name }}</span></x-ui.button>
                                    <x-ui.button variant="tertiary" wire:click="resetPassword('{{ $user->uuid }}')" wire:confirm="Buat sandi baru untuk {{ $user->name }}? Sandi lama langsung tidak berlaku.">Reset sandi<span class="sr-only"> {{ $user->name }}</span></x-ui.button>
                                </span>
                            </x-ui.td>
                        </tr>
                    @endforeach
                </tbody>
            </x-ui.table>
            <div class="mt-4">{{ $users->links() }}</div>
        @endif
    </x-ui.card>

    <x-ui.modal name="form-pengguna" :title="$editingId ? 'Ubah pengguna' : 'Tambah pengguna'">
        <form id="form-pengguna" wire:submit="save" class="flex flex-col gap-4">
            <x-ui.input label="Nama" name="name" required wire:model="name" />
            <x-ui.input label="Email" name="email" type="email" required wire:model="email" />
            <x-ui.input label="Nomor telepon" name="phone" type="tel" inputmode="tel" wire:model="phone" />
            <fieldset class="flex flex-col gap-1">
                <legend class="text-sm font-semibold">Role <span class="text-danger" aria-hidden="true">*</span></legend>
                <div class="grid gap-x-3 md:grid-cols-2">
                    @foreach ($roleOptions as $key => $label)
                        <label class="flex min-h-11 items-center gap-3 text-base">
                            <input type="checkbox" value="{{ $key }}" wire:model.live="roles" class="size-5 accent-primary">
                            {{ $label }}
                        </label>
                    @endforeach
                </div>
                @error('roles')
                    <p role="alert" class="flex items-start gap-1 text-sm text-danger"><x-ui.icon name="circle-alert" size="sm" class="mt-0.5" />{{ $message }}</p>
                @enderror
            </fieldset>
            @if (in_array('ustadz', $roles, true))
                <div class="grid gap-4 md:grid-cols-2">
                    <x-ui.input label="NIP" name="nip" wire:model="nip" />
                    <x-ui.input label="Spesialisasi" name="specialization" wire:model="specialization" />
                </div>
            @endif
            <label class="flex min-h-11 items-center gap-3 text-base">
                <input type="checkbox" wire:model="is_active" class="size-5 accent-primary">
                Akun aktif
            </label>
        </form>
        <x-slot:footer>
            <x-ui.button variant="secondary" x-on:click="open = false">Batal</x-ui.button>
            <x-ui.button type="submit" form="form-pengguna" wire:loading.attr="disabled" wire:target="save">Simpan</x-ui.button>
        </x-slot:footer>
    </x-ui.modal>
</div>
