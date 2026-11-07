@php
    $tz = config('sanad.display_timezone');
    $labels = ['draft' => 'Draf', 'open' => 'Dibuka', 'closed' => 'Ditutup'];
@endphp

<div class="flex flex-col gap-4">
    @if ($canManage)
        <div class="flex justify-end"><x-ui.button icon="plus" wire:click="create">Tambah Periode</x-ui.button></div>
    @endif

    @forelse ($periods as $period)
        <x-ui.card wire:key="p-{{ $period->uuid }}">
            <div class="flex flex-wrap items-start justify-between gap-2">
                <div class="min-w-0">
                    <h2 class="text-lg font-semibold break-words">{{ $period->name }}</h2>
                    <p class="font-tabular text-sm">{{ $period->open_at->setTimezone($tz)->translatedFormat('j M Y, H.i') }} sampai {{ $period->close_at->setTimezone($tz)->translatedFormat('j M Y, H.i') }}</p>
                    <p class="text-sm text-text-muted">Kuota {{ $period->quota }}, biaya {{ \App\Modules\Finance\Support\Rupiah::format($period->registration_fee) }}, {{ $period->applicants_count }} pendaftar</p>
                </div>
                <span class="shrink-0 rounded-full border border-border px-2.5 py-0.5 text-sm font-semibold">{{ $labels[$period->status] }}</span>
            </div>

            <h3 class="mt-4 text-sm font-semibold">Tahap seleksi, total bobot <span class="font-tabular">{{ $period->steps->sum('weight') }}%</span></h3>
            <ul class="mt-1 flex flex-col">
                @foreach ($period->steps as $step)
                    <li wire:key="s-{{ $step->uuid }}" class="flex flex-wrap items-center justify-between gap-2 border-b border-border py-1 last:border-b-0">
                        <span class="min-w-0 text-base break-words">{{ $step->name }} <span class="text-sm text-text-muted">{{ $types[$step->type] }}{{ $step->exam ? ': '.$step->exam->title : '' }}, {{ $step->weight }}%</span></span>
                        @if ($canManage)
                            <span class="flex shrink-0 gap-1">
                                <button type="button" wire:click="moveStep('{{ $step->uuid }}', -1)" class="inline-flex size-11 items-center justify-center rounded-control hover:bg-primary-soft" aria-label="Naikkan {{ $step->name }}"><x-ui.icon name="chevron-up" /></button>
                                <button type="button" wire:click="moveStep('{{ $step->uuid }}', 1)" class="inline-flex size-11 items-center justify-center rounded-control hover:bg-primary-soft" aria-label="Turunkan {{ $step->name }}"><x-ui.icon name="chevron-down" /></button>
                                <button type="button" wire:click="removeStep('{{ $step->uuid }}')" class="inline-flex size-11 items-center justify-center rounded-control hover:bg-primary-soft" aria-label="Hapus {{ $step->name }}"><x-ui.icon name="x" /></button>
                            </span>
                        @endif
                    </li>
                @endforeach
            </ul>

            @if ($canManage && $period->status !== 'closed')
                <form wire:submit="addStep('{{ $period->uuid }}')" class="mt-3 grid gap-3 md:grid-cols-[1fr_160px_100px_auto] md:items-start">
                    <x-ui.input label="Nama tahap" name="step_name" wire:model="step_name" />
                    <x-ui.select label="Jenis" name="step_type" :options="$types" wire:model.live="step_type" />
                    <x-ui.input label="Bobot (%)" name="step_weight" inputmode="numeric" wire:model="step_weight" />
                    <x-ui.button type="submit" variant="secondary" class="md:mt-6" wire:loading.attr="disabled">Tambah Tahap</x-ui.button>
                    @if ($step_type === 'exam')
                        <div class="md:col-span-4"><x-ui.select label="Ujian untuk calon santri" name="step_exam" placeholder="Pilih" :options="$exams" wire:model="step_exam" /></div>
                    @endif
                </form>
                <div class="mt-3 flex flex-wrap gap-2">
                    <x-ui.button variant="secondary" wire:click="edit('{{ $period->uuid }}')">Ubah<span class="sr-only"> {{ $period->name }}</span></x-ui.button>
                    @if ($period->status === 'draft')
                        <x-ui.button wire:click="open('{{ $period->uuid }}')" wire:confirm="Buka pendaftaran? Halaman publik langsung menerima pendaftar.">Buka Pendaftaran</x-ui.button>
                    @else
                        <x-ui.button variant="danger" wire:click="closePeriod('{{ $period->uuid }}')" wire:confirm="Tutup pendaftaran?">Tutup Pendaftaran</x-ui.button>
                    @endif
                </div>
                @error('period-'.$period->uuid)<x-ui.banner tone="danger" class="mt-2" role="alert">{{ $message }}</x-ui.banner>@enderror
            @endif
        </x-ui.card>
    @empty
        <x-ui.card><x-ui.empty-state icon="calendar-range" message="Belum ada periode PPDB." class="py-2" /></x-ui.card>
    @endforelse

    <x-ui.modal name="form-periode" :title="$editing ? 'Ubah periode' : 'Tambah periode'">
        <div class="flex flex-col gap-3">
            <x-ui.input label="Nama periode" name="name" required help="Contoh: Gelombang 1 2027." wire:model="name" />
            <x-ui.select label="Tahun ajaran" name="academic_year_id" placeholder="Belum ditentukan" :options="$years" wire:model="academic_year_id" />
            <x-ui.input label="Dibuka" name="open_at" type="datetime-local" required wire:model="open_at" />
            <x-ui.input label="Ditutup" name="close_at" type="datetime-local" required wire:model="close_at" />
            <div class="grid grid-cols-2 gap-3">
                <x-ui.input label="Kuota" name="quota" inputmode="numeric" required wire:model="quota" />
                <x-ui.input label="Biaya daftar (Rp)" name="registration_fee" inputmode="numeric" required wire:model="registration_fee" />
            </div>
        </div>
        <x-slot:footer>
            <x-ui.button variant="secondary" x-on:click="open = false">Batal</x-ui.button>
            <x-ui.button wire:click="save" wire:loading.attr="disabled" wire:target="save">Simpan</x-ui.button>
        </x-slot:footer>
    </x-ui.modal>
</div>
