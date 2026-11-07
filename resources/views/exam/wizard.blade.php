<div class="mx-auto flex w-full max-w-[860px] flex-col gap-4">
    <ol class="flex items-start justify-between gap-2 rounded-card border border-border bg-surface p-3" aria-label="Langkah">
        @foreach ([1 => 'Info', 2 => 'Soal', 3 => 'Pengaturan', 4 => 'Sasaran'] as $number => $label)
            <li @class(['flex min-w-0 flex-1 flex-col items-center gap-1 text-center text-xs', 'font-semibold text-primary' => $step === $number, 'text-text-muted' => $step !== $number]) @if ($step === $number) aria-current="step" @endif>
                <span @class(['font-tabular flex size-7 items-center justify-center rounded-full border', 'border-primary bg-primary text-on-primary' => $step >= $number, 'border-border' => $step < $number])>{{ $number }}</span>{{ $label }}
            </li>
        @endforeach
    </ol>

    @if ($step === 1)
        <x-ui.card title="Info dan jadwal">
            <div class="flex flex-col gap-3">
                <x-ui.input label="Judul" name="title" required wire:model="title" />
                <x-ui.select label="Mapel" name="subject_id" placeholder="Pilih" :options="$subjects" wire:model="subject_id" />
                <div class="grid gap-3 md:grid-cols-3">
                    <x-ui.input label="Dibuka" name="start_at" type="datetime-local" required wire:model="start_at" />
                    <x-ui.input label="Ditutup" name="end_at" type="datetime-local" required wire:model="end_at" />
                    <x-ui.input label="Durasi (menit)" name="duration" inputmode="numeric" required wire:model="duration" />
                </div>
            </div>
        </x-ui.card>
    @elseif ($step === 2)
        <x-ui.card title="Pilih soal">
            <div class="flex flex-col gap-3">
                <x-ui.select label="Bank soal" name="bank_id" placeholder="Pilih" :options="$bankOptions" wire:model.live="bank_id" />
                <div class="grid gap-3 md:grid-cols-[1fr_120px_auto] md:items-start">
                    <x-ui.input label="Acak per tag" name="random_tag" help="Kosongkan untuk semua soal bank." wire:model="random_tag" />
                    <x-ui.input label="Jumlah" name="random_count" inputmode="numeric" wire:model="random_count" />
                    <x-ui.button variant="secondary" class="md:mt-6" wire:click="addRandom" wire:loading.attr="disabled" wire:target="addRandom">Tambah Acak</x-ui.button>
                </div>
                @foreach ($bankQuestions as $question)
                    <label wire:key="pilih-{{ $question->uuid }}" class="flex min-h-11 items-start gap-3 border-b border-border py-2 last:border-b-0">
                        <input type="checkbox" class="mt-1 size-5 shrink-0 accent-primary" @checked(in_array($question->id, $chosenIds, true)) wire:click="toggleQuestion('{{ $question->uuid }}')">
                        <span class="min-w-0 flex-1 text-base break-words" dir="{{ $question->direction }}">{!! strip_tags($question->body) !!}</span>
                    </label>
                @endforeach
            </div>
        </x-ui.card>
        <x-ui.card title="Soal terpilih">
            <p class="text-base"><strong class="font-tabular">{{ $chosen->count() }}</strong> soal, total <strong class="font-tabular">{{ $totalPoints }}</strong> poin.</p>
        </x-ui.card>
    @elseif ($step === 3)
        <x-ui.card title="Pengaturan">
            <div class="flex flex-col gap-3">
                <label class="flex min-h-11 items-center gap-3 text-base"><input type="checkbox" wire:model="shuffle_questions" class="size-5 accent-primary">Acak urutan soal per santri</label>
                <label class="flex min-h-11 items-center gap-3 text-base"><input type="checkbox" wire:model="shuffle_options" class="size-5 accent-primary">Acak urutan opsi per santri</label>
                <div class="grid gap-3 md:grid-cols-3">
                    <x-ui.input label="Batas percobaan" name="max_attempts" inputmode="numeric" required wire:model="max_attempts" />
                    <x-ui.input label="Kode akses" name="access_code" autocomplete="off" help="Kosongkan bila tidak perlu." wire:model="access_code" />
                    <x-ui.input label="Nilai lulus" name="pass_score" inputmode="decimal" wire:model="pass_score" />
                </div>
                <x-ui.select label="Tampilkan hasil" name="show_result_mode" :options="['immediately' => 'Langsung setelah selesai', 'after_close' => 'Setelah ujian ditutup', 'never' => 'Tidak ditampilkan']" wire:model="show_result_mode" />
            </div>
        </x-ui.card>
    @else
        <x-ui.card title="Sasaran">
            @can('ppdb.manage')
                <div class="mb-3 max-w-xs"><x-ui.select label="Peserta" name="audience" :options="['student' => 'Santri', 'applicant' => 'Calon santri (PPDB)']" wire:model.live="audience" /></div>
            @endcan
            @if ($audience === 'applicant')
                <p class="text-base">Ujian dibuka untuk pendaftar yang masuk seleksi, setelah dipilih di tahap seleksi periode PPDB.</p>
            @else
            <fieldset>
                <legend class="text-sm font-semibold">Kelas</legend>
                <div class="grid gap-1 md:grid-cols-3">
                    @foreach ($classroomOptions as $classroom)
                        <label wire:key="kelas-{{ $classroom->id }}" class="flex min-h-11 items-center gap-3 text-base"><input type="checkbox" value="{{ $classroom->id }}" wire:model.live="classrooms" class="size-5 accent-primary">{{ $classroom->name }}</label>
                    @endforeach
                </div>
            </fieldset>
            <div class="mt-3">
                <x-ui.input label="Tambah santri satu per satu" name="student_search" type="search" autocomplete="off" wire:model.live.debounce.250ms="student_search" />
                @foreach ($studentResults as $result)
                    <button type="button" wire:key="s-{{ $result->uuid }}" wire:click="addStudent('{{ $result->uuid }}')" class="mt-1 flex min-h-11 w-full items-center justify-between gap-2 rounded-control px-2 text-left hover:bg-primary-soft"><span class="font-semibold break-words">{{ $result->name }}</span><span class="font-tabular shrink-0 text-sm text-text-muted">{{ $result->nis }}</span></button>
                @endforeach
                @foreach ($targetStudents as $target)
                    <p wire:key="t-{{ $target->id }}" class="mt-1 flex min-h-11 items-center justify-between gap-2 text-base"><span class="min-w-0 break-words">{{ $target->name }}</span><button type="button" wire:click="removeStudent({{ $target->id }})" class="inline-flex size-11 items-center justify-center rounded-control hover:bg-primary-soft" aria-label="Hapus {{ $target->name }}"><x-ui.icon name="x" /></button></p>
                @endforeach
            </div>
            @endif
        </x-ui.card>
        <x-ui.card title="Tinjau">
            <p class="text-base">{{ $exam->title }}: {{ $chosen->count() }} soal, {{ $totalPoints }} poin, {{ $exam->duration_minutes }} menit, {{ $participantCount }} peserta.</p>
            @if ($problems !== [])
                <ul class="mt-2 flex flex-col gap-1">
                    @foreach ($problems as $problem)
                        <li class="flex items-start gap-1 text-sm text-danger"><x-ui.icon name="circle-alert" size="sm" class="mt-0.5" />{{ $problem }}</li>
                    @endforeach
                </ul>
            @endif
            @error('publish')<x-ui.banner tone="danger" class="mt-2" role="alert">{{ $message }}</x-ui.banner>@enderror
        </x-ui.card>
    @endif

    <div class="flex flex-wrap justify-between gap-2">
        @if ($step > 1)
            <x-ui.button variant="secondary" wire:click="back">Sebelumnya</x-ui.button>
        @else
            <span></span>
        @endif
        @if ($step < 4)
            <x-ui.button wire:click="next" wire:loading.attr="disabled" wire:target="next">Lanjut</x-ui.button>
        @else
            <x-ui.button wire:click="publish" wire:confirm="Terbitkan ujian ini? Peserta langsung melihatnya." wire:loading.attr="disabled" wire:target="publish">Terbitkan</x-ui.button>
        @endif
    </div>
</div>
