@php $tz = config('sanad.display_timezone'); @endphp

<div class="flex flex-col gap-4">
    <x-ui.card title="Tentang data wajah">
        <ul class="flex list-disc flex-col gap-2 pl-5 text-base">
            <li><strong>Yang disimpan:</strong> deretan angka hasil olahan foto (embedding), bukan foto wajahnya.</li>
            <li><strong>Tujuannya:</strong> hanya untuk mencatat kehadiran. Tidak dipakai untuk hal lain dan tidak pernah diekspor.</li>
            <li><strong>Lama simpan:</strong> selama persetujuan aktif dan santri masih aktif. Terhapus saat dicabut, lulus, atau keluar.</li>
            <li><strong>Hak Anda:</strong> bebas menolak atau mencabut kapan saja. Santri tetap bisa absen dengan kartu atau manual.</li>
        </ul>
    </x-ui.card>

    @forelse ($children as $child)
        @php($records = $history[$child->id] ?? collect())
        @php($active = $records->firstWhere('revoked_at', null))
        <x-ui.card wire:key="c-{{ $child->uuid }}" :title="$child->name">
            <p class="flex items-center gap-2 text-base font-semibold">
                <x-ui.icon :name="$active ? 'circle-check' : 'circle-dashed'" />
                {{ $active ? 'Disetujui' : 'Belum disetujui' }}{{ in_array($child->id, $enrolled, true) ? ', wajah sudah terdaftar' : '' }}
            </p>
            <div class="mt-3">
                @if ($active)
                    <x-ui.button variant="danger" class="w-full md:w-auto" wire:click="revoke('{{ $child->uuid }}')" wire:confirm="Cabut persetujuan untuk {{ $child->name }}? Data wajahnya langsung dihapus." wire:loading.attr="disabled" wire:target="revoke">Cabut Persetujuan<span class="sr-only"> {{ $child->name }}</span></x-ui.button>
                @else
                    <x-ui.button class="w-full md:w-auto" wire:click="agree('{{ $child->uuid }}')" wire:loading.attr="disabled" wire:target="agree">Setuju<span class="sr-only"> {{ $child->name }}</span></x-ui.button>
                @endif
            </div>
            @if ($records->isNotEmpty())
                <h3 class="mt-4 text-sm font-semibold">Riwayat</h3>
                <ul class="mt-1 flex flex-col gap-1 text-sm">
                    @foreach ($records as $item)
                        <li>Disetujui {{ $item->granted_at->setTimezone($tz)->translatedFormat('j M Y') }}{{ $item->method === 'paper' ? ' (kertas)' : '' }}{{ $item->revoked_at ? ', dicabut '.$item->revoked_at->setTimezone($tz)->translatedFormat('j M Y') : '' }}</li>
                    @endforeach
                </ul>
            @endif
        </x-ui.card>
    @empty
        <x-ui.card><x-ui.empty-state icon="users" message="Belum ada anak yang terhubung." class="py-2" /></x-ui.card>
    @endforelse
</div>
