<div class="flex flex-col gap-4">
    <x-ui.banner tone="warning"><strong>Data sensitif.</strong> {{ $canManage ? 'Setiap pembukaan detail tercatat.' : 'Anda hanya melihat ringkasan. Keluhan dan obat hanya untuk petugas UKS.' }}</x-ui.banner>

    @if ($canManage)
        <x-ui.card title="Catat kunjungan">
            <form wire:submit="save" class="flex flex-col gap-3">
                @if ($student)
                    <p class="flex flex-wrap items-center justify-between gap-2 rounded-control bg-primary-soft p-3 text-base"><strong class="min-w-0 break-words">{{ $student->name }}</strong><button type="button" wire:click="$set('studentUuid', '')" class="inline-flex min-h-11 items-center text-sm font-semibold text-primary underline">Ganti</button></p>
                @else
                    <x-ui.input label="Cari santri" name="search" type="search" autocomplete="off" wire:model.live.debounce.250ms="search" />
                    @foreach ($results as $result)
                        <button type="button" wire:key="s-{{ $result->uuid }}" wire:click="chooseStudent('{{ $result->uuid }}')" class="flex min-h-11 w-full items-center justify-between gap-2 rounded-control px-2 text-left hover:bg-primary-soft"><span class="font-semibold break-words">{{ $result->name }}</span><span class="font-tabular shrink-0 text-sm text-text-muted">{{ $result->nis }}</span></button>
                    @endforeach
                @endif
                <div class="grid gap-3 md:grid-cols-2">
                    <x-ui.input label="Tanggal" name="visit_date" type="date" required wire:model="visit_date" />
                    <x-ui.select label="Hasil" name="outcome" required :options="\App\Modules\Affairs\Models\HealthRecord::OUTCOMES" wire:model="outcome" />
                </div>
                <x-ui.textarea label="Keluhan" name="complaint" required wire:model="complaint" />
                <x-ui.textarea label="Tindakan" name="action" wire:model="action" />
                <x-ui.input label="Obat" name="medication" wire:model="medication" />
                <p class="text-sm text-text-muted">Wali diberi tahu bila dirujuk atau dipulangkan, tanpa isi keluhan.</p>
                <x-ui.button type="submit" class="self-start" wire:loading.attr="disabled" wire:target="save">Simpan</x-ui.button>
            </form>
        </x-ui.card>
    @endif

    <x-ui.card title="Riwayat kunjungan">
        @if ($records->isEmpty())
            <x-ui.empty-state icon="heart-pulse" message="Belum ada catatan kesehatan." class="py-2" />
        @else
            <x-ui.table caption="Riwayat kunjungan">
                <thead><tr><x-ui.th>Tanggal</x-ui.th><x-ui.th>Santri</x-ui.th><x-ui.th>Hasil</x-ui.th>@if ($canManage)<x-ui.th><span class="sr-only">Detail</span></x-ui.th>@endif</tr></thead>
                <tbody>
                    @foreach ($records as $record)
                        <tr wire:key="h-{{ $record->uuid }}">
                            <x-ui.td label="Tanggal">{{ $record->visit_date->translatedFormat('j M Y') }}</x-ui.td>
                            <x-ui.td label="Santri"><span class="break-words">{{ $record->student->name }}</span></x-ui.td>
                            <x-ui.td label="Hasil">{{ \App\Modules\Affairs\Models\HealthRecord::OUTCOMES[$record->outcome] }}</x-ui.td>
                            @if ($canManage)
                                <x-ui.td label="Detail"><x-ui.button variant="tertiary" wire:click="open('{{ $record->uuid }}')">Buka<span class="sr-only"> {{ $record->student->name }}</span></x-ui.button></x-ui.td>
                            @endif
                        </tr>
                    @endforeach
                </tbody>
            </x-ui.table>
        @endif
        <div class="mt-3">{{ $records->links() }}</div>
    </x-ui.card>

    <x-ui.modal name="detail-kesehatan" title="Detail kunjungan">
        @if ($detail)
            <dl class="flex flex-col gap-2 text-base">
                <div><dt class="text-sm text-text-muted">Santri</dt><dd class="break-words">{{ $detail['name'] }}</dd></div>
                <div><dt class="text-sm text-text-muted">Keluhan</dt><dd class="break-words whitespace-pre-line">{{ $detail['complaint'] }}</dd></div>
                <div><dt class="text-sm text-text-muted">Tindakan</dt><dd class="break-words whitespace-pre-line">{{ $detail['action'] ?: '-' }}</dd></div>
                <div><dt class="text-sm text-text-muted">Obat</dt><dd class="break-words">{{ $detail['medication'] ?: '-' }}</dd></div>
            </dl>
        @endif
    </x-ui.modal>
</div>
