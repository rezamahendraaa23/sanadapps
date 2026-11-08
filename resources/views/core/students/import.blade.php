{{-- Impor santri dalam langkah bernomor; dipakai sebagai popup di halaman Santri maupun halaman penuh. --}}
<div @class(['flex w-full flex-col gap-5', 'mx-auto max-w-[720px] rounded-card border border-border bg-surface p-4 shadow-sm md:p-6' => ! $inModal])>
    @if ($import)
        <section class="flex flex-col gap-3" aria-live="polite">
            <h3 class="text-base font-semibold">Hasil impor</h3>
            @if (in_array($import->status, ['queued', 'processing'], true))
                <div wire:poll.2s></div>
                <x-ui.skeleton label="Sedang mengimpor" />
                <p class="text-base">Sedang mengimpor {{ $import->total_rows }} baris. Tunggu sebentar, hasilnya muncul sendiri.</p>
            @elseif ($import->status === 'failed')
                <x-ui.error-state :message="$import->errors[0]['messages'][0] ?? 'Impor gagal.'">
                    <x-slot:action><x-ui.button wire:click="startOver">Unggah Ulang</x-ui.button></x-slot:action>
                </x-ui.error-state>
            @else
                <x-ui.banner :tone="$import->failed_rows ? 'warning' : 'success'">
                    {{ $import->success_rows }} santri berhasil diimpor{{ $import->failed_rows ? ', '.$import->failed_rows.' baris gagal' : '' }}.
                </x-ui.banner>
                <p class="text-sm text-text-muted">Santri hasil impor belum punya sandi. Buat sandinya dari halaman detail santri saat diperlukan.</p>
                <div class="flex flex-col gap-2 md:flex-row md:justify-end">
                    @if ($import->failed_rows)
                        <x-ui.button variant="secondary" icon="file-down" :href="route('staff.students.import.errors', $import)">Unduh Baris Gagal</x-ui.button>
                    @endif
                    <x-ui.button variant="secondary" wire:click="startOver">Impor Lagi</x-ui.button>
                    <x-ui.button :href="route('staff.students.index')" wire:navigate>Lihat Santri</x-ui.button>
                </div>
            @endif
        </section>
    @elseif ($preview)
        <section class="flex flex-col gap-4">
            <h3 class="flex items-center gap-2 text-base font-semibold"><span class="inline-flex size-7 items-center justify-center rounded-full bg-primary text-sm text-on-primary">3</span>Periksa sebelum impor</h3>
            <dl class="grid grid-cols-3 gap-2 text-center">
                <div class="rounded-control bg-bg p-3"><dt class="text-sm text-text-muted">Total baris</dt><dd class="font-display font-tabular text-2xl font-semibold">{{ $preview['total'] }}</dd></div>
                <div class="rounded-control bg-success-soft p-3"><dt class="text-sm text-text-muted">Siap diimpor</dt><dd class="font-display font-tabular text-2xl font-semibold text-success">{{ $preview['valid'] }}</dd></div>
                <div @class(['rounded-control p-3', 'bg-danger-soft' => $preview['invalid'], 'bg-bg' => ! $preview['invalid']])><dt class="text-sm text-text-muted">Bermasalah</dt><dd @class(['font-display font-tabular text-2xl font-semibold', 'text-danger' => $preview['invalid']])>{{ $preview['invalid'] }}</dd></div>
            </dl>

            @if ($preview['errors'])
                <div>
                    <p class="text-sm font-semibold">Baris bermasalah (tidak ikut diimpor)</p>
                    <ul class="mt-2 flex max-h-64 flex-col gap-2 overflow-y-auto">
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
                </div>
            @endif

            <div class="flex flex-col-reverse gap-2 md:flex-row md:justify-end">
                <x-ui.button variant="secondary" wire:click="startOver">Pilih Berkas Lain</x-ui.button>
                @if ($preview['valid'] > 0)
                    <x-ui.button icon="check" wire:click="start" wire:loading.attr="disabled" wire:target="start">Impor {{ $preview['valid'] }} Santri</x-ui.button>
                @endif
            </div>
        </section>
    @else
        <section class="flex flex-col gap-3">
            <h3 class="flex items-center gap-2 text-base font-semibold"><span class="inline-flex size-7 items-center justify-center rounded-full bg-primary text-sm text-on-primary">1</span>Unduh template</h3>
            <p class="text-base">Isi data santri dan wali di template. Nama kelas dan asrama harus sama persis dengan yang sudah dibuat di Kelas &amp; Asrama.</p>
            <x-ui.button variant="secondary" icon="file-down" class="self-start" :href="route('staff.students.import.template')">Unduh Template</x-ui.button>
        </section>
        <form wire:submit="check" class="flex flex-col gap-3 border-t border-border pt-5">
            <h3 class="flex items-center gap-2 text-base font-semibold"><span class="inline-flex size-7 items-center justify-center rounded-full bg-primary text-sm text-on-primary">2</span>Unggah berkas yang sudah diisi</h3>
            <x-ui.file-drop label="Berkas Excel" name="file" accept=".xlsx" hint="Format .xlsx sesuai template, maksimal 10 MB" icon="file-spreadsheet" wire:model="file" />
            <div class="flex justify-end">
                <x-ui.button type="submit" class="w-full md:w-auto" wire:loading.attr="disabled" wire:target="check,file">
                    <span wire:loading.remove wire:target="check">Periksa Berkas</span>
                    <span wire:loading wire:target="check">Memeriksa</span>
                </x-ui.button>
            </div>
        </form>
    @endif
</div>
