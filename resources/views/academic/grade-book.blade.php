<div class="flex flex-col gap-4">
    @can('reports.publish')
        <div class="flex justify-end"><x-ui.button variant="secondary" icon="file-text" :href="route('staff.report-cards.index')" wire:navigate>Rapor</x-ui.button></div>
    @endcan

    @if ($options === [])
        <x-ui.card><x-ui.empty-state icon="graduation-cap" message="Belum ada penugasan mengajar. Hubungi admin akademik." class="py-2" /></x-ui.card>
    @else
        <x-ui.card>
            <x-ui.select label="Mapel dan kelas" name="assignmentKey" :options="$options" wire:model.live="assignmentKey" />
        </x-ui.card>

        @if ($assessment)
            <x-ui.card :title="$assessment->title">
                <x-slot:actions>
                    <x-ui.button variant="tertiary" wire:click="closeAssessment">Kembali</x-ui.button>
                </x-slot:actions>
                <p class="mb-3 text-sm text-text-muted">Nilai maksimum {{ $assessment->max_score }}.</p>
                @if ($students->isEmpty())
                    <x-ui.empty-state icon="users" message="Kelas ini belum punya santri aktif." class="py-2" />
                @else
                    <form wire:submit="saveScores">
                        <ul class="flex flex-col">
                            @foreach ($students as $student)
                                <li wire:key="n-{{ $student->id }}" class="flex flex-wrap items-center justify-between gap-2 border-b border-border py-2 last:border-b-0">
                                    <label for="nilai-{{ $student->id }}" class="min-w-0 flex-1 text-base break-words">
                                        {{ $student->name }}
                                        @if (in_array($student->id, $lockedStudents, true))
                                            <span class="inline-flex items-center gap-1 text-xs text-text-muted"><x-ui.icon name="lock" size="sm" />Rapor terbit</span>
                                        @endif
                                    </label>
                                    <span class="w-24 shrink-0">
                                        <input id="nilai-{{ $student->id }}" type="text" inputmode="decimal" autocomplete="off" wire:model="scores.{{ $student->id }}" @error('scores.'.$student->id) aria-invalid="true" @enderror
                                            x-on:keydown.enter.prevent="$el.closest('li').nextElementSibling?.querySelector('input')?.focus()"
                                            class="font-tabular min-h-11 w-full rounded-control border border-text-muted bg-surface px-2 text-right text-base aria-invalid:border-danger">
                                    </span>
                                    @error('scores.'.$student->id)
                                        <p role="alert" class="w-full text-right text-sm text-danger">{{ $message }}</p>
                                    @enderror
                                </li>
                            @endforeach
                        </ul>
                        @if ($lockedStudents !== [])
                            <div class="mt-3"><x-ui.input label="Alasan mengubah nilai yang terkunci" name="unlockReason" wire:model="unlockReason" /></div>
                        @else
                            @error('unlockReason')<p role="alert" class="mt-2 text-sm text-danger">{{ $message }}</p>@enderror
                        @endif
                        <x-ui.button type="submit" class="mt-4 w-full md:w-auto" wire:loading.attr="disabled" wire:target="saveScores">Simpan Nilai</x-ui.button>
                    </form>
                @endif
            </x-ui.card>
        @else
            <x-ui.card title="Penilaian">
                @if ($hasTerm)
                    <x-slot:actions><x-ui.button icon="plus" wire:click="createAssessment">Tambah Penilaian</x-ui.button></x-slot:actions>
                @endif
                @forelse ($assessments as $item)
                    <button type="button" wire:key="p-{{ $item->uuid }}" wire:click="openAssessment('{{ $item->uuid }}')" class="flex min-h-11 w-full items-center justify-between gap-2 border-b border-border py-2 text-left last:border-b-0 hover:bg-primary-soft">
                        <span class="min-w-0"><span class="block text-base break-words">{{ $item->title }}</span><span class="block text-sm text-text-muted">{{ $item->component->name }}, {{ $item->grades_count }} dari {{ $students->count() }} terisi</span></span>
                        <x-ui.icon name="chevron-right" class="shrink-0" />
                    </button>
                @empty
                    <x-ui.empty-state icon="file-pen-line" message="Belum ada penilaian di term ini." class="py-2" />
                @endforelse
            </x-ui.card>

            @if ($assessments->isNotEmpty() && $students->isNotEmpty())
                <x-ui.card title="Nilai akhir sementara">
                    <ul class="flex flex-col">
                        @foreach ($students as $student)
                            @php($final = $finals[$student->id] ?? null)
                            <li wire:key="f-{{ $student->id }}" class="flex justify-between gap-3 border-b border-border py-2 text-base last:border-b-0">
                                <span class="min-w-0 break-words">{{ $student->name }}</span>
                                <span class="font-tabular shrink-0 font-semibold">
                                    {{ $final === null ? '-' : rtrim(rtrim(number_format($final, 2, ',', ''), '0'), ',') }}
                                    @if ($final !== null && $final < $subject->kkm)<span class="block text-xs font-normal text-danger">Di bawah KKM</span>@endif
                                </span>
                            </li>
                        @endforeach
                    </ul>
                </x-ui.card>
            @endif
        @endif
    @endif

    <x-ui.modal name="form-penilaian" title="Tambah penilaian">
        <div class="flex flex-col gap-3">
            <x-ui.input label="Judul" name="title" required wire:model="title" />
            <x-ui.select label="Komponen" name="component_id" placeholder="Pilih" :options="$components" wire:model="component_id" />
            <x-ui.input label="Nilai maksimum" name="max_score" inputmode="numeric" required wire:model="max_score" />
            <x-ui.input label="Tanggal" name="assessed_on" type="date" wire:model="assessed_on" />
        </div>
        <x-slot:footer>
            <x-ui.button variant="secondary" x-on:click="open = false">Batal</x-ui.button>
            <x-ui.button wire:click="saveAssessment" wire:loading.attr="disabled" wire:target="saveAssessment">Simpan</x-ui.button>
        </x-slot:footer>
    </x-ui.modal>
</div>
