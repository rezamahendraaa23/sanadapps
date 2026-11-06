@php
    $doneCount = collect($steps)->where('done', true)->count();
    $percent = $steps ? (int) round($doneCount / count($steps) * 100) : 0;
@endphp

<div class="grid gap-4 min-[80rem]:grid-cols-[minmax(0,2fr)_minmax(0,1fr)]">
    <div class="flex min-w-0 flex-col gap-4">
        @if ($steps)
            <x-ui.card>
                <h2 class="rule-double pb-2 text-xl font-semibold">Setup awal pesantren</h2>
                <div class="mt-3 flex items-center gap-3">
                    <div class="h-2 flex-1 rounded-full bg-border" role="progressbar" aria-valuenow="{{ $percent }}" aria-valuemin="0" aria-valuemax="100" aria-label="Progres setup">
                        <div class="h-2 rounded-full bg-primary" style="width: {{ $percent }}%"></div>
                    </div>
                    <span class="font-tabular text-sm font-semibold">{{ $doneCount }} dari {{ count($steps) }}</span>
                </div>
                <ol class="mt-3 flex flex-col">
                    @foreach ($steps as $step)
                        <li wire:key="step-{{ $step['key'] }}" class="flex flex-wrap items-center gap-3 border-b border-border py-2 last:border-b-0">
                            @if ($step['done'])
                                <x-ui.icon name="circle-check" class="text-success" />
                            @else
                                <x-ui.icon name="circle-dashed" class="text-text-muted" />
                            @endif
                            <div class="min-w-0 flex-1 basis-48">
                                <a href="{{ route($step['route']) }}" class="font-semibold text-primary hover:underline">{{ $step['label'] }}</a>
                                <p class="text-sm text-text-muted">{{ $step['done'] ? 'Selesai.' : $step['hint'] }}</p>
                            </div>
                            @if (! $step['done'] && $step['can_mark'])
                                <x-ui.button variant="tertiary" wire:click="mark('{{ $step['key'] }}')">Tandai selesai<span class="sr-only">: {{ $step['label'] }}</span></x-ui.button>
                            @endif
                        </li>
                    @endforeach
                </ol>
                @if ($doneCount === count($steps))
                    <div class="mt-3 flex justify-end">
                        <x-ui.button variant="secondary" wire:click="dismissChecklist">Sembunyikan Daftar Ini</x-ui.button>
                    </div>
                @endif
            </x-ui.card>
        @endif

        <x-ui.card title="Perlu tindakan">
            <x-ui.empty-state message="Tidak ada yang menunggu. Verifikasi transfer, izin, dan koreksi ujian akan muncul di sini setelah modulnya berjalan." class="py-2" />
        </x-ui.card>
    </div>

    <div class="flex min-w-0 flex-col gap-4">
        @if ($activeStudents !== null)
            <x-ui.hero-card label="Santri aktif" :value="number_format($activeStudents, 0, ',', '.')">
                @if ($activeStudents === 0)
                    Belum ada santri.
                @endif
                <x-slot:action>
                    <x-ui.button variant="on-primary" :href="route('staff.students.index')">Lihat Santri</x-ui.button>
                </x-slot:action>
            </x-ui.hero-card>
        @endif
        @if ($classrooms !== null || $guardians !== null)
            <x-ui.card>
                <dl class="grid grid-cols-2 gap-3">
                    @if ($classrooms !== null)
                        <div><dt class="text-sm text-text-muted">Kelas</dt><dd class="font-display font-tabular text-2xl font-semibold">{{ $classrooms }}</dd></div>
                    @endif
                    @if ($guardians !== null)
                        <div><dt class="text-sm text-text-muted">Wali</dt><dd class="font-display font-tabular text-2xl font-semibold">{{ $guardians }}</dd></div>
                    @endif
                </dl>
            </x-ui.card>
        @endif
    </div>
</div>
