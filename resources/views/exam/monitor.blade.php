@php
    $labels = ['belum' => ['Belum mulai', 'circle-dashed'], 'mengerjakan' => ['Mengerjakan', 'pencil'], 'selesai' => ['Selesai', 'circle-check'], 'terputus' => ['Terputus', 'wifi-off']];
    $tz = config('sanad.display_timezone');
@endphp

<div class="flex flex-col gap-4" @if ($exam->status === 'published') wire:poll.5s @endif>
    <div class="flex flex-wrap items-center justify-between gap-2">
        <h2 class="font-display min-w-0 text-xl font-semibold break-words">{{ $exam->title }}</h2>
        <div class="flex flex-wrap gap-2">
            <x-ui.button variant="secondary" :href="$resultsUrl" wire:navigate>Hasil</x-ui.button>
            @if ($exam->status === 'published')
                <x-ui.button variant="danger" x-data x-on:click="$dispatch('open-modal', 'tutup-ujian')">Tutup Ujian</x-ui.button>
            @endif
        </div>
    </div>

    <dl class="grid grid-cols-[repeat(auto-fit,minmax(10rem,1fr))] gap-2">
        @foreach ($labels as $key => [$label, $icon])
            <div class="rounded-card border border-border bg-surface p-3">
                <dt class="flex items-center gap-1 text-sm text-text-muted"><x-ui.icon :name="$icon" size="sm" />{{ $label }}</dt>
                <dd class="font-display font-tabular text-3xl font-semibold">{{ $counts[$key] ?? 0 }}</dd>
            </div>
        @endforeach
    </dl>

    <x-ui.card>
        @if ($rows->isEmpty())
            <x-ui.empty-state icon="users" message="Belum ada peserta sasaran." class="py-2" />
        @else
            <x-ui.table caption="Peserta ujian">
                <thead><tr><x-ui.th>Santri</x-ui.th><x-ui.th>Status</x-ui.th><x-ui.th>Progres</x-ui.th><x-ui.th numeric>Pindah layar</x-ui.th><x-ui.th>Terakhir terlihat</x-ui.th></tr></thead>
                <tbody>
                    @foreach ($rows as $row)
                        <tr wire:key="p-{{ $row['student']->uuid }}">
                            <x-ui.td label="Santri"><span class="break-words">{{ $row['student']->name }}</span></x-ui.td>
                            <x-ui.td label="Status"><span @class(['inline-flex items-center gap-1 text-sm font-semibold', 'text-danger' => $row['state'] === 'terputus'])><x-ui.icon :name="$labels[$row['state']][1]" size="sm" />{{ $labels[$row['state']][0] }}</span></x-ui.td>
                            <x-ui.td label="Progres">
                                @if ($row['attempt'])
                                    @php($answered = min($row['attempt']->answers_count, $total))
                                    <span class="font-tabular text-sm">{{ $answered }}/{{ $total }}</span>
                                    <span class="mt-1 block h-2 w-full max-w-40 overflow-hidden rounded-full bg-primary-soft"><span class="block h-full bg-primary" style="width: {{ $total ? round($answered / $total * 100) : 0 }}%"></span></span>
                                @else
                                    -
                                @endif
                            </x-ui.td>
                            <x-ui.td label="Pindah layar" class="text-right"><span class="font-tabular">{{ $row['attempt']?->blur_count ?? 0 }}</span></x-ui.td>
                            <x-ui.td label="Terakhir terlihat">{{ $row['attempt']?->last_seen_at?->setTimezone($tz)->format('H.i.s') ?? '-' }}</x-ui.td>
                        </tr>
                    @endforeach
                </tbody>
            </x-ui.table>
        @endif
        <p class="mt-3 text-xs text-text-muted">Diperbarui pukul {{ $updatedAt }}.</p>
    </x-ui.card>

    <x-ui.modal name="tutup-ujian" title="Tutup ujian?">
        <p class="text-base">Peserta yang masih mengerjakan langsung terkumpul otomatis dan tidak bisa melanjutkan.</p>
        <x-slot:footer>
            <x-ui.button variant="secondary" x-on:click="open = false">Batal</x-ui.button>
            <x-ui.button variant="danger" wire:click="closeExam" wire:loading.attr="disabled" wire:target="closeExam">Tutup Ujian</x-ui.button>
        </x-slot:footer>
    </x-ui.modal>
</div>
