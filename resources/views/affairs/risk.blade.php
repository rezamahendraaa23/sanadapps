<div class="flex flex-col gap-4">
    <div class="flex flex-wrap items-center justify-between gap-2">
        <p class="text-sm text-text-muted">{{ $latest ? 'Dihitung '.\Carbon\CarbonImmutable::parse($latest)->translatedFormat('j F Y').'. Otomatis tiap hari pukul 03.00.' : 'Belum pernah dihitung.' }}</p>
        <x-ui.button variant="secondary" icon="refresh-cw" wire:click="compute" wire:loading.attr="disabled" wire:target="compute">Hitung Sekarang</x-ui.button>
    </div>

    <div role="tablist" aria-label="Tingkat risiko" class="flex flex-wrap gap-1">
        @foreach (\App\Modules\Affairs\Models\StudentRiskScore::LEVELS as $key => $label)
            <x-ui.tab :selected="$level === $key" wire:key="tab-{{ $key }}" wire:click="$set('level', '{{ $key }}')">{{ $label }} ({{ $counts[$key] ?? 0 }})</x-ui.tab>
        @endforeach
    </div>

    <x-ui.card>
        <p class="mb-3 text-sm text-text-muted">Alat bantu perhatian, bukan penilaian atas santri.</p>
        @forelse ($scores as $score)
            <div wire:key="s-{{ $score->id }}" class="flex flex-wrap items-start justify-between gap-2 border-b border-border py-3 last:border-b-0">
                <div class="min-w-0 flex-1">
                    <a href="{{ route('staff.students.show', $score->student) }}" wire:navigate class="font-semibold text-primary underline break-words">{{ $score->student->name }}</a>
                    <ul class="mt-1 flex flex-col gap-0.5 text-sm">
                        @forelse ($score->reasons as $reason)
                            <li>{{ $reason['text'] }} <span class="font-tabular text-text-muted">(+{{ $reason['points'] }})</span></li>
                        @empty
                            <li class="text-text-muted">Tidak ada sinyal.</li>
                        @endforelse
                    </ul>
                </div>
                <span class="font-display font-tabular shrink-0 text-2xl font-semibold">{{ $score->score }}</span>
            </div>
        @empty
            <x-ui.empty-state icon="circle-check" message="Tidak ada santri di tingkat ini." class="py-2" />
        @endforelse
        <div class="mt-3">{{ $scores->links() }}</div>
    </x-ui.card>

    @if ($canConfigure)
        <x-ui.card title="Bobot sinyal">
            <form wire:submit="saveWeights" class="grid gap-3 md:grid-cols-3">
                @foreach ($labels as $key => $label)
                    <x-ui.input :label="$label" :name="'weights.'.$key" inputmode="numeric" required wire:model="weights.{{ $key }}" />
                @endforeach
                <x-ui.button type="submit" variant="secondary" class="self-start md:col-span-3 md:justify-self-start">Simpan Bobot</x-ui.button>
            </form>
        </x-ui.card>
    @endif
</div>
