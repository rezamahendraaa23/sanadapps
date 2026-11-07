<div class="flex flex-col gap-4">
    <div class="flex flex-wrap justify-between gap-2">
        <x-ui.button variant="tertiary" icon="chevron-left" :href="$examsUrl" wire:navigate>Daftar Ujian</x-ui.button>
        <x-ui.button icon="plus" x-data x-on:click="$dispatch('open-modal', 'form-bank')">Bank Baru</x-ui.button>
    </div>

    <x-ui.card title="Bank soal">
        @forelse ($banks as $item)
            <button type="button" wire:key="b-{{ $item->uuid }}" wire:click="$set('bankUuid', '{{ $item->uuid }}')" @if ($bank?->is($item)) aria-current="true" @endif @class(['flex min-h-11 w-full items-center justify-between gap-2 rounded-control px-2 py-2 text-left', 'bg-primary-soft' => $bank?->is($item), 'hover:bg-primary-soft' => ! $bank?->is($item)])>
                <span class="min-w-0"><span class="block font-semibold break-words">{{ $item->name }}</span><span class="block text-sm text-text-muted">{{ $item->subject->name }}, {{ $item->questions_count }} soal</span></span>
                <x-ui.icon name="chevron-right" class="shrink-0" />
            </button>
        @empty
            <x-ui.empty-state icon="book-marked" message="Belum ada bank soal." class="py-2" />
        @endforelse
    </x-ui.card>

    @if ($bank)
        <x-ui.card :title="$bank->name">
            <x-slot:actions>
                <div class="flex flex-wrap gap-2">
                    <x-ui.button variant="secondary" icon="file-up" x-data x-on:click="$dispatch('open-modal', 'impor-soal')">Impor</x-ui.button>
                    <x-ui.button icon="plus" wire:click="create">Tambah Soal</x-ui.button>
                </div>
            </x-slot:actions>
            @if ($imported !== null)
                <x-ui.banner :tone="$importErrors === [] ? 'success' : 'warning'" class="mb-3">
                    {{ $imported }} soal masuk.
                    @foreach ($importErrors ?? [] as $error)
                        <span class="block text-sm">Baris {{ $error['row'] }}: {{ implode(' ', $error['errors']) }}</span>
                    @endforeach
                </x-ui.banner>
            @endif
            @forelse ($questions as $question)
                <div wire:key="q-{{ $question->uuid }}" class="flex flex-wrap items-start justify-between gap-2 border-b border-border py-3 last:border-b-0">
                    <div class="min-w-0 flex-1">
                        <p class="text-sm text-text-muted">{{ $types[$question->type] }}, {{ (float) $question->points }} poin{{ $question->tags ? ', '.implode(', ', $question->tags) : '' }}</p>
                        <div dir="{{ $question->direction }}" @class(['break-words', 'font-arabic text-[1.375rem] leading-[2]' => $question->direction === 'rtl', 'text-base' => $question->direction === 'ltr']) @if ($question->direction === 'rtl') lang="ar" @endif>{!! $question->body !!}</div>
                    </div>
                    <div class="flex shrink-0 gap-1">
                        <x-ui.button variant="tertiary" wire:click="edit('{{ $question->uuid }}')">Ubah</x-ui.button>
                        <x-ui.button variant="tertiary" wire:click="remove('{{ $question->uuid }}')" wire:confirm="Hapus soal ini dari bank? Ujian yang sudah memakainya tidak berubah.">Hapus</x-ui.button>
                    </div>
                </div>
            @empty
                <x-ui.empty-state icon="file-pen-line" message="Belum ada soal. Tambahkan satu per satu atau impor dari Excel." class="py-2" />
            @endforelse
        </x-ui.card>
    @endif

    <x-ui.modal name="form-bank" title="Bank soal baru">
        <div class="flex flex-col gap-3">
            <x-ui.input label="Nama bank" name="bank_name" required wire:model="bank_name" />
            <x-ui.select label="Mapel" name="bank_subject" placeholder="Pilih" :options="$subjects" wire:model="bank_subject" />
        </div>
        <x-slot:footer>
            <x-ui.button variant="secondary" x-on:click="open = false">Batal</x-ui.button>
            <x-ui.button wire:click="saveBank" wire:loading.attr="disabled" wire:target="saveBank">Simpan</x-ui.button>
        </x-slot:footer>
    </x-ui.modal>

    <x-ui.modal name="impor-soal" title="Impor soal">
        <div class="flex flex-col gap-3">
            <p class="text-base">Jenis: pg, kompleks, bs, isian, esai. Arah: ltr atau rtl. Kunci kompleks dipisah koma, jawaban isian dipisah garis tegak.</p>
            <x-ui.button variant="secondary" icon="file-down" class="self-start" :href="route('exam.import-template')">Unduh Template</x-ui.button>
            <div class="flex flex-col gap-1">
                <label for="field-importFile" class="text-sm font-semibold">Berkas Excel (.xlsx)</label>
                <input id="field-importFile" type="file" accept=".xlsx" wire:model="importFile" class="min-h-11 w-full text-base">
                @error('importFile')<p role="alert" class="text-sm text-danger">{{ $message }}</p>@enderror
            </div>
        </div>
        <x-slot:footer>
            <x-ui.button variant="secondary" x-on:click="open = false">Tutup</x-ui.button>
            <x-ui.button wire:click="import" wire:loading.attr="disabled" wire:target="import,importFile">Impor</x-ui.button>
        </x-slot:footer>
    </x-ui.modal>

    <x-ui.modal name="form-soal" :title="$editing ? 'Ubah soal' : 'Tambah soal'">
        <div class="flex flex-col gap-3">
            @error('body')<x-ui.banner tone="danger" role="alert">{{ $message }}</x-ui.banner>@enderror
            <div class="grid grid-cols-2 gap-3">
                <x-ui.select label="Jenis" name="type" :options="$types" wire:model.live="type" />
                <x-ui.select label="Arah teks" name="direction" :options="['ltr' => 'Latin', 'rtl' => 'Arab']" wire:model.live="direction" />
            </div>
            <div class="flex flex-col gap-1">
                <label for="field-body" class="text-sm font-semibold">Soal</label>
                <textarea id="field-body" rows="4" dir="{{ $direction }}" wire:model.live.blur="body" class="w-full rounded-control border border-text-muted bg-surface p-3 {{ $direction === 'rtl' ? 'font-arabic text-[1.375rem] leading-[2]' : 'text-base' }}"></textarea>
                <p class="text-sm text-text-muted">Format yang diterima: b, i, u, p, ul, ol, li, br.</p>
            </div>

            @if (in_array($type, ['mcq', 'multi'], true))
                <fieldset class="flex flex-col gap-2">
                    <legend class="text-sm font-semibold">Opsi dan kunci</legend>
                    @foreach ($options as $index => $option)
                        @php($letter = chr(65 + $index))
                        <div wire:key="opsi-{{ $index }}" class="flex items-center gap-2">
                            @if ($type === 'mcq')
                                <input type="radio" value="{{ $letter }}" wire:model="singleKey" class="size-5 shrink-0 accent-primary" aria-label="Kunci {{ $letter }}">
                            @else
                                <input type="checkbox" value="{{ $letter }}" wire:model="keys" class="size-5 shrink-0 accent-primary" aria-label="Kunci {{ $letter }}">
                            @endif
                            <span class="font-tabular w-5 shrink-0 font-semibold">{{ $letter }}</span>
                            <input type="text" dir="{{ $direction }}" wire:model="options.{{ $index }}" aria-label="Opsi {{ $letter }}" class="min-h-11 w-full min-w-0 flex-1 rounded-control border border-text-muted bg-surface px-3 {{ $direction === 'rtl' ? 'font-arabic text-xl' : 'text-base' }}">
                            <button type="button" wire:click="removeOption({{ $index }})" class="inline-flex size-11 shrink-0 items-center justify-center rounded-control hover:bg-primary-soft" aria-label="Hapus opsi {{ $letter }}"><x-ui.icon name="x" /></button>
                        </div>
                    @endforeach
                    <x-ui.button variant="tertiary" icon="plus" class="self-start" wire:click="addOption">Tambah Opsi</x-ui.button>
                </fieldset>
            @elseif ($type === 'true_false')
                <x-ui.select label="Kunci" name="singleKey" placeholder="Pilih" :options="['true' => 'Benar', 'false' => 'Salah']" wire:model="singleKey" />
            @elseif ($type === 'short')
                <x-ui.input label="Jawaban yang diterima" name="singleKey" help="Lebih dari satu? Pisahkan dengan garis tegak: sholat|shalat" wire:model="singleKey" />
            @endif

            <div class="grid grid-cols-2 gap-3">
                <x-ui.input label="Poin" name="points" inputmode="decimal" required wire:model="points" />
                <x-ui.select label="Tingkat" name="difficulty" placeholder="Tidak diisi" :options="[1 => 'Mudah', 2 => 'Sedang', 3 => 'Sulit']" wire:model="difficulty" />
            </div>
            <x-ui.input label="Tag" name="tags" help="Pisahkan dengan koma, untuk pemilihan acak." wire:model="tags" />
            <div class="flex flex-col gap-1">
                <label for="field-media" class="text-sm font-semibold">Gambar</label>
                <input id="field-media" type="file" accept="image/jpeg,image/png" wire:model="media" class="min-h-11 w-full text-base">
                @error('media')<p role="alert" class="text-sm text-danger">{{ $message }}</p>@enderror
            </div>
            @if (trim(strip_tags($preview)) !== '')
                <div>
                    <p class="text-sm font-semibold">Pratinjau tampilan santri</p>
                    <div dir="{{ $direction }}" class="mt-1 rounded-control bg-bg p-3 break-words {{ $direction === 'rtl' ? 'font-arabic text-[1.5rem] leading-[2]' : 'text-lg' }}">{!! $preview !!}</div>
                </div>
            @endif
        </div>
        <x-slot:footer>
            <x-ui.button variant="secondary" x-on:click="open = false">Batal</x-ui.button>
            <x-ui.button wire:click="save" wire:loading.attr="disabled" wire:target="save,media">Simpan</x-ui.button>
        </x-slot:footer>
    </x-ui.modal>
</div>
