@php
    $statusLabels = \App\Modules\Exam\Models\ExamAttempt::STATUSES;
@endphp

<div class="flex flex-col gap-4">
    <div class="flex flex-wrap items-center justify-between gap-2">
        <h2 class="font-display min-w-0 text-xl font-semibold break-words">{{ $exam->title }}</h2>
        <div class="flex flex-wrap gap-2">
            <x-ui.button variant="secondary" :href="$monitorUrl" wire:navigate>Monitor</x-ui.button>
            <x-ui.button variant="secondary" icon="file-down" :href="route('exam.results-export', $exam)">Ekspor</x-ui.button>
            <x-ui.button x-data x-on:click="$dispatch('open-modal', 'kirim-nilai')">Kirim ke Nilai</x-ui.button>
        </div>
    </div>

    @if ($essay)
        <x-ui.card title="Koreksi esai">
            <div class="grid gap-4 md:grid-cols-2">
                <div class="min-w-0">
                    <p class="text-sm text-text-muted">{{ $essay->attempt->student?->name ?? $essay->attempt->applicant?->name }}</p>
                    <div dir="{{ $essay->question->direction }}" class="mt-1 text-base break-words">{!! $essay->question->body !!}</div>
                    <p dir="{{ $essay->question->direction }}" class="mt-2 rounded-control bg-bg p-3 text-base break-words whitespace-pre-line">{{ $essay->answer['value'] ?? '' }}</p>
                </div>
                <form wire:submit="gradeEssay('{{ $essay->uuid }}')" class="flex flex-col gap-3">
                    <x-ui.input label="Poin (maksimal {{ (float) $essay->question->points }})" name="essayScore" inputmode="decimal" required wire:model="essayScore" />
                    <x-ui.button type="submit" wire:loading.attr="disabled" wire:target="gradeEssay">Simpan dan Berikutnya</x-ui.button>
                </form>
            </div>
        </x-ui.card>
    @endif

    <div class="grid gap-4 lg:grid-cols-2">
        <x-ui.card title="Ringkasan" class="min-w-0">
            <p class="text-base">{{ $attempts->count() }} peserta selesai. Rata-rata <strong class="font-tabular">{{ $average ?? '-' }}</strong>.</p>
            <table class="mt-3 w-full text-sm">
                <caption class="sr-only">Sebaran nilai</caption>
                <tbody>
                    @foreach ($buckets as $bucket => $count)
                        <tr>
                            <th scope="row" class="font-tabular w-16 py-0.5 text-left font-normal">{{ $bucket * 10 }}-{{ $bucket === 9 ? 100 : $bucket * 10 + 9 }}</th>
                            <td class="py-0.5"><span class="block h-3 rounded-full bg-primary" style="width: {{ $count ? max(4, round($count / $maxBucket * 100)) : 0 }}%"></span></td>
                            <td class="font-tabular w-8 py-0.5 text-right">{{ $count }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </x-ui.card>
        <x-ui.card title="Soal tersulit" class="min-w-0">
            @forelse ($items->take(10) as $item)
                <div wire:key="butir-{{ $item['question']->id }}" class="flex justify-between gap-3 border-b border-border py-2 last:border-b-0">
                    <span class="min-w-0 text-sm break-words" dir="{{ $item['question']->direction }}">{{ \Illuminate\Support\Str::limit(strip_tags($item['question']->body), 90) }}</span>
                    <span class="font-tabular shrink-0 text-sm font-semibold">{{ $item['percent'] }}% benar</span>
                </div>
            @empty
                <x-ui.empty-state icon="chart-column" message="Belum ada data." class="py-2" />
            @endforelse
        </x-ui.card>
    </div>

    <x-ui.card title="Nilai peserta">
        @if ($attempts->isEmpty())
            <x-ui.empty-state icon="users" message="Belum ada peserta yang selesai." class="py-2" />
        @else
            <x-ui.table caption="Nilai peserta">
                <thead><tr><x-ui.th>Santri</x-ui.th><x-ui.th>Status</x-ui.th><x-ui.th numeric>Pindah layar</x-ui.th><x-ui.th numeric>Nilai</x-ui.th></tr></thead>
                <tbody>
                    @foreach ($attempts as $attempt)
                        <tr wire:key="a-{{ $attempt->uuid }}">
                            <x-ui.td label="Santri"><span class="break-words">{{ $attempt->student?->name ?? $attempt->applicant?->name }}</span></x-ui.td>
                            <x-ui.td label="Status">{{ $statusLabels[$attempt->status] }}</x-ui.td>
                            <x-ui.td label="Pindah layar" class="text-right"><span class="font-tabular">{{ $attempt->blur_count }}</span></x-ui.td>
                            <x-ui.td label="Nilai" class="text-right"><span class="font-tabular font-semibold">{{ $attempt->score === null ? '-' : rtrim(rtrim(number_format($attempt->score, 2, ',', ''), '0'), ',') }}</span></x-ui.td>
                        </tr>
                    @endforeach
                </tbody>
            </x-ui.table>
        @endif
    </x-ui.card>

    <x-ui.modal name="kirim-nilai" title="Kirim ke buku nilai">
        <div class="flex flex-col gap-3">
            <p class="text-base">Hanya peserta yang sudah selesai dinilai. Kirim ulang memperbarui nilai, tidak menggandakan.</p>
            <x-ui.select label="Kelas" name="push_classroom" placeholder="Pilih" :options="$classrooms" wire:model="push_classroom" />
            <x-ui.select label="Komponen nilai" name="push_component" placeholder="Pilih" :options="$components" wire:model="push_component" />
        </div>
        <x-slot:footer>
            <x-ui.button variant="secondary" x-on:click="open = false">Batal</x-ui.button>
            <x-ui.button wire:click="push" wire:loading.attr="disabled" wire:target="push">Kirim</x-ui.button>
        </x-slot:footer>
    </x-ui.modal>
</div>
