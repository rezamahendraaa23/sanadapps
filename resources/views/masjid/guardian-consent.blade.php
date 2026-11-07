<div class="flex flex-col gap-4">
    <x-ui.card>
        <p class="text-base">Layar di masjid dilihat banyak orang. Nama anak hanya tampil di sana, misalnya sebagai imam piket atau ucapan selamat, bila Anda setuju. Persetujuan bisa dicabut kapan saja dan namanya hilang dari layar dalam hitungan detik.</p>
    </x-ui.card>
    @forelse ($children as $child)
        @php($scopes = ($active[$child->id] ?? collect())->pluck('scope')->all())
        <x-ui.card wire:key="c-{{ $child->uuid }}" :title="$child->name">
            @foreach (['name' => 'Nama boleh tampil di layar', 'photo' => 'Foto boleh tampil di layar'] as $scope => $label)
                <label class="flex min-h-11 items-center gap-3 text-base">
                    <input type="checkbox" @checked(in_array($scope, $scopes, true)) wire:click="toggle('{{ $child->uuid }}', '{{ $scope }}')" class="size-5 accent-primary">
                    {{ $label }}
                </label>
            @endforeach
        </x-ui.card>
    @empty
        <x-ui.card><x-ui.empty-state icon="users" message="Belum ada anak yang terhubung." class="py-2" /></x-ui.card>
    @endforelse
</div>
