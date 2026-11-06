<div class="mx-auto flex w-full max-w-[720px] flex-col gap-4">
    @if ($import)
        <x-ui.card title="Hasil impor" :wire:poll.2s="in_array($import->status, ['queued', 'processing'], true) ? '' : null">
            @if (in_array($import->status, ['queued', 'processing'], true))
                <x-ui.skeleton label="Sedang mengimpor" />
                <p class="mt-3 text-base">Sedang mengimpor {{ $import->total_rows }} baris. Halaman ini akan diperbarui sendiri.</p>
            @elseif ($import->status === 'failed')
                <x-ui.error-state :message="$import->errors[0]['messages'][0] ?? 'Impor gagal.'">
                    <x-slot:action><x-ui.button wire:click="startOver">Unggah Ulang</x-ui.button></x-slot:action>
                </x-ui.error-state>
            @else
                <x-ui.banner :tone="$import->failed_rows ? 'warning' : 'success'">
                    {{ $import->success_rows }} santri berhasil diimpor{{ $import->failed_rows ? ', '.$import->failed_rows.' baris gagal' : '' }}.
                </x-ui.banner>
                <p class="mt-3 text-sm text-text-muted">Santri hasil impor belum punya sandi. Buat sandinya dari halaman detail santri saat diperlukan.</p>
                <div class="mt-4 flex flex-col gap-2 md:flex-row">
                    @if ($import->failed_rows)
                        <x-ui.button variant="secondary" icon="file-down" :href="route('staff.students.import.errors', $import)">Unduh Baris Gagal</x-ui.button>
                    @endif
                    <x-ui.button variant="secondary" wire:click="startOver">Impor Lagi</x-ui.button>
                    <x-ui.button :href="route('staff.students.index')">Lihat Santri</x-ui.button>
                </div>
            @endif
        </x-ui.card>
    @elseif ($preview)
        <x-ui.card title="Pratinjau">
            <dl class="grid grid-cols-3 gap-3 text-center">
                <div><dt class="text-sm text-text-muted">Total baris</dt><dd class="font-display font-tabular text-2xl font-semibold">{{ $preview['total'] }}</dd></div>
                <div><dt class="text-sm text-text-muted">Siap diimpor</dt><dd class="font-display font-tabular text-2xl font-semibold text-success">{{ $preview['valid'] }}</dd></div>
                <div><dt class="text-sm text-text-muted">Bermasalah</dt><dd class="font-display font-tabular text-2xl font-semibold {{ $preview['invalid'] ? 'text-danger' : '' }}">{{ $preview['invalid'] }}</dd></div>
            </dl>

            @if ($preview['errors'])
                <h3 class="mt-4 text-base font-semibold">Baris bermasalah</h3>
                <ul class="mt-2 flex flex-col gap-2">
                    @foreach ($preview['errors'] as $error)
                        <li class="rounded-control bg-danger-soft p-3 text-sm">
                            <strong>Baris {{ $error['row'] }}{{ $error['name'] ? ', '.$error['name'] : '' }}:</strong>
                            {{ implode(' ', $error['messages']) }}
                        </li>
                    @endforeach
                </ul>
                @if ($preview['invalid'] > count($preview['errors']))
                    <p class="mt-2 text-sm text-text-muted">Ditampilkan {{ count($preview['errors']) }} baris pertama. Sisanya ada di laporan setelah impor.</p>
                @endif
            @endif

            <div class="mt-4 flex flex-col-reverse gap-2 md:flex-row md:justify-end">
                <x-ui.button variant="secondary" wire:click="startOver">Pilih Berkas Lain</x-ui.button>
                @if ($preview['valid'] > 0)
                    <x-ui.button wire:click="start" wire:loading.attr="disabled" wire:target="start">Impor {{ $preview['valid'] }} Santri</x-ui.button>
                @endif
            </div>
        </x-ui.card>
    @else
        <x-ui.card title="Langkah 1: unduh template">
            <p class="text-base">Isi template dengan data santri dan wali. Kelas dan asrama harus sudah dibuat, tulis namanya persis sama.</p>
            <x-ui.button variant="secondary" icon="file-down" class="mt-3" :href="route('staff.students.import.template')">Unduh Template</x-ui.button>
        </x-ui.card>
        <x-ui.card title="Langkah 2: unggah berkas">
            <form wire:submit="check" class="flex flex-col gap-3">
                <div class="flex flex-col gap-1">
                    <label for="field-file" class="text-sm font-semibold">Berkas Excel (.xlsx), maksimal 10 MB</label>
                    <input id="field-file" type="file" accept=".xlsx" wire:model="file" @error('file') aria-invalid="true" @enderror class="min-h-11 w-full min-w-0 text-sm">
                    <div wire:loading wire:target="file" class="text-sm text-text-muted">Mengunggah…</div>
                    @error('file')
                        <p role="alert" class="flex items-start gap-1 text-sm text-danger"><x-ui.icon name="circle-alert" size="sm" class="mt-0.5" />{{ $message }}</p>
                    @enderror
                </div>
                <div class="flex justify-end">
                    <x-ui.button type="submit" class="w-full md:w-auto" wire:loading.attr="disabled" wire:target="check,file">
                        <span wire:loading.remove wire:target="check">Periksa Berkas</span>
                        <span wire:loading wire:target="check">Memeriksa…</span>
                    </x-ui.button>
                </div>
            </form>
        </x-ui.card>
    @endif
</div>
