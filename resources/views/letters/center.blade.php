<div class="flex flex-col gap-4">
    <div role="tablist" aria-label="Bagian surat" class="flex flex-wrap gap-1">
        @foreach (['buat' => 'Buat Surat', 'arsip' => 'Arsip', 'template' => 'Template', 'pengaturan' => 'Kop & Penomoran'] as $key => $label)
            <x-ui.tab :selected="$tab === $key" wire:key="tab-{{ $key }}" wire:click="selectTab('{{ $key }}')">{{ $label }}</x-ui.tab>
        @endforeach
    </div>

    @if ($tab === 'buat')
        @if (! $template)
            <section aria-label="Pilih jenis surat" class="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                @foreach ($templates->where('is_active', true) as $option)
                    <button type="button" wire:key="pick-{{ $option->uuid }}" wire:click="pickTemplate('{{ $option->uuid }}')" class="group flex min-h-36 flex-col gap-3 rounded-card border border-border bg-surface p-4 text-left shadow-sm transition-all hover:-translate-y-0.5 hover:border-primary hover:shadow-md">
                        <span class="inline-flex size-11 -rotate-6 items-center justify-center rounded-card bg-primary-soft text-primary group-hover:bg-primary group-hover:text-on-primary"><x-ui.icon name="file-text" /></span>
                        <span class="font-semibold break-words">{{ $option->name }}</span>
                        <span class="font-tabular mt-auto text-xs text-text-muted">Kode {{ $option->code }}{{ $option->needs_student ? ', untuk satu santri' : '' }}</span>
                    </button>
                @endforeach
            </section>
        @elseif ($issued)
            <x-ui.card>
                <div class="flex flex-col items-center gap-3 py-6 text-center">
                    <span class="inline-flex size-16 items-center justify-center rounded-full bg-success text-on-primary"><x-ui.icon name="check" size="lg" /></span>
                    <h2 class="font-display text-2xl font-semibold">Surat terbit</h2>
                    <p class="font-tabular text-lg font-semibold">{{ $issued->letter_no }}</p>
                    <div class="flex flex-wrap justify-center gap-2">
                        <x-ui.button icon="download" :href="route('staff.letters.pdf', $issued)">Unduh PDF</x-ui.button>
                        <x-ui.button variant="secondary" wire:click="startNew">Buat Surat Lain</x-ui.button>
                    </div>
                </div>
            </x-ui.card>
        @else
            <div class="grid gap-4 xl:grid-cols-[minmax(0,1fr)_minmax(0,1fr)]">
                <x-ui.card :title="$template->name">
                    <x-slot:actions><x-ui.button variant="tertiary" wire:click="startNew">Ganti Jenis</x-ui.button></x-slot:actions>
                    <div class="flex flex-col gap-3">
                        @if ($template->needs_student)
                            @if ($student)
                                <div class="flex items-center justify-between gap-2 rounded-control bg-primary-soft p-3">
                                    <span class="min-w-0"><span class="block font-semibold break-words">{{ $student->name }}</span><span class="block text-sm text-text-muted">{{ $student->nis }}{{ $student->classroom ? ', '.$student->classroom->name : '' }}</span></span>
                                    <x-ui.button variant="tertiary" wire:click="clearStudent">Ganti</x-ui.button>
                                </div>
                            @else
                                <x-ui.input label="Cari santri" name="studentSearch" type="search" autocomplete="off" help="Ketik nama atau NIS." wire:model.live.debounce.250ms="studentSearch" />
                                @foreach ($studentResults as $result)
                                    <button type="button" wire:key="st-{{ $result->uuid }}" wire:click="pickStudent('{{ $result->uuid }}')" class="flex min-h-11 items-center justify-between gap-2 rounded-control border border-border px-3 text-left hover:border-primary">
                                        <span class="break-words">{{ $result->name }}</span><span class="font-tabular text-sm text-text-muted">{{ $result->nis }}</span>
                                    </button>
                                @endforeach
                            @endif
                        @endif
                        <x-ui.input label="Kepada" name="recipient" help="Contoh: Ketua RT 03 RW 05 Desa Sukamaju." wire:model.live.debounce.400ms="recipient" />
                        @foreach ($template->fields as $field)
                            <x-ui.input wire:key="f-{{ $field['key'] }}" :label="$field['label']" name="extra.{{ $field['key'] }}" wire:model.live.debounce.400ms="extra.{{ $field['key'] }}" />
                        @endforeach
                        <div class="grid gap-3 md:grid-cols-2">
                            <x-ui.input label="Tanggal surat" name="issued_on" type="date" required wire:model.live="issued_on" />
                            <x-ui.select label="Kop" name="letterheadUuid" placeholder="Tanpa kop gambar" :options="$letterheads->pluck('name', 'uuid')->all()" wire:model.live="letterheadUuid" />
                            <x-ui.input label="Penandatangan" name="signer_name" required wire:model.live.debounce.400ms="signer_name" />
                            <x-ui.input label="Jabatan" name="signer_title" required wire:model.live.debounce.400ms="signer_title" />
                        </div>
                        <details class="rounded-control border border-border p-3">
                            <summary class="min-h-11 cursor-pointer content-center font-semibold">Ubah perihal dan isi untuk surat ini</summary>
                            <div class="mt-2 flex flex-col gap-3">
                                <x-ui.input label="Perihal" name="subject" required wire:model.live.debounce.400ms="subject" />
                                <x-ui.textarea label="Isi surat" name="body" rows="12" required wire:model.live.debounce.600ms="body" />
                            </div>
                        </details>
                        @error('issue')<p role="alert" class="flex items-center gap-1 text-sm font-semibold text-danger"><x-ui.icon name="circle-alert" size="sm" />{{ $message }}</p>@enderror
                        <x-ui.button icon="stamp" wire:click="issue" wire:loading.attr="disabled" wire:target="issue">Terbitkan dan Beri Nomor</x-ui.button>
                    </div>
                </x-ui.card>

                {{-- Pratinjau seperti kertas A4. --}}
                <section aria-label="Pratinjau surat" class="min-w-0">
                    <div class="mx-auto aspect-[210/297] w-full max-w-[42rem] overflow-hidden rounded-card bg-surface shadow-lg ring-1 ring-border">
                        <div class="flex h-full flex-col text-[0.62rem] leading-relaxed md:text-xs">
                            @php($kop = $letterheads->firstWhere('uuid', $letterheadUuid))
                            @if ($kop?->header_path)
                                <img src="{{ route('staff.letters.kop', [$kop, 'header']) }}" alt="" class="w-full">
                            @else
                                <div class="border-b-2 border-text px-[8%] py-3 text-center"><p class="font-display text-base font-semibold">{{ app(\App\Support\SchoolIdentity::class)->displayName() }}</p></div>
                            @endif
                            <div class="flex-1 overflow-hidden px-[9%] py-4">
                                <div class="flex justify-between gap-4">
                                    <table class="text-left"><tbody>
                                        <tr><td class="pr-2">Nomor</td><td>: <span class="font-tabular">{{ $preview['number'] }}</span> <span class="text-text-muted">(perkiraan)</span></td></tr>
                                        <tr><td class="pr-2">Perihal</td><td>: <b>{{ $preview['subject'] }}</b></td></tr>
                                    </tbody></table>
                                    <p class="shrink-0">{{ $preview['date'] }}</p>
                                </div>
                                @if (trim($recipient) !== '')
                                    <p class="mt-3">Kepada Yth.<br><b>{{ $recipient }}</b><br>di tempat</p>
                                @endif
                                <p class="mt-3 whitespace-pre-line">{{ $preview['body'] }}</p>
                                <div class="mt-4 ml-auto w-1/2 text-center">
                                    <p>{{ $signer_title }}</p>
                                    <p class="mt-8 font-semibold underline">{{ $signer_name ?: '(nama penandatangan)' }}</p>
                                </div>
                            </div>
                            @if ($kop?->footer_path)
                                <img src="{{ route('staff.letters.kop', [$kop, 'footer']) }}" alt="" class="w-full">
                            @endif
                        </div>
                    </div>
                    @if ($preview['missing'] !== [])
                        <p class="mt-2 flex items-center gap-1 text-sm font-semibold text-warning"><x-ui.icon name="triangle-alert" size="sm" />Belum diisi: {{ collect($preview['missing'])->map(fn ($key) => $builtIn[$key] ?? str_replace('_', ' ', $key))->implode(', ') }}</p>
                    @endif
                </section>
            </div>
        @endif
    @elseif ($tab === 'arsip')
        <x-ui.card>
            <div class="mb-3 md:max-w-md"><x-ui.input label="Cari nomor, perihal, penerima, atau santri" name="search" type="search" wire:model.live.debounce.300ms="search" /></div>
            @if ($letters->isEmpty())
                <x-ui.empty-state icon="file-text" message="Belum ada surat." class="py-2" />
            @else
                <x-ui.table caption="Arsip surat keluar">
                    <thead><tr><x-ui.th>Nomor</x-ui.th><x-ui.th>Perihal</x-ui.th><x-ui.th>Untuk</x-ui.th><x-ui.th>Aksi</x-ui.th></tr></thead>
                    <tbody>
                        @foreach ($letters as $letter)
                            <tr wire:key="l-{{ $letter->uuid }}">
                                <x-ui.td label="Nomor"><span @class(['font-tabular font-semibold', 'line-through text-text-muted' => $letter->status === 'void'])>{{ $letter->letter_no }}</span><span class="block text-xs text-text-muted">{{ $letter->issued_on->translatedFormat('j M Y') }}{{ $letter->status === 'void' ? ', dibatalkan: '.$letter->void_reason : '' }}</span></x-ui.td>
                                <x-ui.td label="Perihal"><span class="break-words">{{ $letter->subject }}</span></x-ui.td>
                                <x-ui.td label="Untuk"><span class="break-words">{{ $letter->student?->name ?? $letter->recipient ?? '-' }}</span></x-ui.td>
                                <x-ui.td label="Aksi">
                                    <div class="flex flex-wrap gap-1">
                                        <x-ui.button variant="tertiary" :href="route('staff.letters.pdf', $letter)">PDF<span class="sr-only"> {{ $letter->letter_no }}</span></x-ui.button>
                                        @if ($letter->status === 'issued')
                                            <x-ui.button variant="tertiary" wire:click="askVoid('{{ $letter->uuid }}')">Batalkan<span class="sr-only"> {{ $letter->letter_no }}</span></x-ui.button>
                                        @endif
                                    </div>
                                </x-ui.td>
                            </tr>
                        @endforeach
                    </tbody>
                </x-ui.table>
                <div class="mt-3">{{ $letters->links() }}</div>
            @endif
        </x-ui.card>
    @elseif ($tab === 'template')
        <div class="flex justify-end"><x-ui.button icon="plus" wire:click="editTemplate">Template Baru</x-ui.button></div>
        <div class="grid gap-3 md:grid-cols-2">
            @foreach ($templates as $item)
                <x-ui.card wire:key="t-{{ $item->uuid }}" class="flex flex-col gap-2">
                    <div class="flex items-start justify-between gap-2">
                        <h3 class="font-semibold break-words">{{ $item->name }}</h3>
                        <span class="font-tabular shrink-0 rounded-full bg-bg px-2 py-0.5 text-xs font-semibold">{{ $item->code }}</span>
                    </div>
                    <p class="line-clamp-3 text-sm whitespace-pre-line text-text-muted">{{ $item->body }}</p>
                    <div class="mt-auto flex flex-wrap gap-2">
                        <x-ui.button variant="secondary" wire:click="editTemplate('{{ $item->uuid }}')">Ubah<span class="sr-only"> {{ $item->name }}</span></x-ui.button>
                        <x-ui.button variant="tertiary" wire:click="toggleTemplate('{{ $item->uuid }}')">{{ $item->is_active ? 'Sembunyikan' : 'Tampilkan' }}<span class="sr-only"> {{ $item->name }}</span></x-ui.button>
                    </div>
                </x-ui.card>
            @endforeach
        </div>
    @else
        <div class="grid gap-4 lg:grid-cols-2">
            <x-ui.card title="Penomoran dan penandatangan">
                <form wire:submit="saveSettings" class="flex flex-col gap-3">
                    <x-ui.input label="Format nomor" name="number_format" required help="Pakai {nomor}, {kode}, {bulan_romawi}, {bulan}, {tahun}. Nomor berurutan per tahun." wire:model.live.debounce.300ms="number_format" />
                    <p class="text-sm">Contoh: <span class="font-tabular font-semibold">{{ $numberExample }}</span></p>
                    <x-ui.input label="Jumlah digit nomor" name="number_digits" inputmode="numeric" required wire:model.live="number_digits" />
                    <x-ui.input label="Kota" name="city" help="Dipakai di isian {kota}." wire:model="city" />
                    <x-ui.input label="Penandatangan bawaan" name="default_signer_name" wire:model="default_signer_name" />
                    <x-ui.input label="Jabatan bawaan" name="default_signer_title" required wire:model="default_signer_title" />
                    <x-ui.button type="submit">Simpan</x-ui.button>
                </form>
            </x-ui.card>
            <x-ui.card title="Kop dan footer">
                @error('kop')<p role="alert" class="mb-2 text-sm font-semibold text-danger">{{ $message }}</p>@enderror
                @forelse ($letterheads as $kop)
                    <div wire:key="kop-{{ $kop->uuid }}" class="mb-3 flex flex-col gap-2 rounded-control border border-border p-3">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <span class="font-semibold break-words">{{ $kop->name }}{{ $kop->is_default ? ', utama' : '' }}</span>
                            <div class="flex gap-1">
                                @unless ($kop->is_default)<x-ui.button variant="tertiary" wire:click="makeDefault('{{ $kop->uuid }}')">Jadikan Utama</x-ui.button>@endunless
                                <x-ui.button variant="tertiary" wire:click="deleteLetterhead('{{ $kop->uuid }}')" wire:confirm="Hapus kop {{ $kop->name }}?">Hapus</x-ui.button>
                            </div>
                        </div>
                        @if ($kop->header_path)<img src="{{ route('staff.letters.kop', [$kop, 'header']) }}" alt="Kop {{ $kop->name }}" class="w-full rounded-control border border-border">@endif
                        @if ($kop->footer_path)<img src="{{ route('staff.letters.kop', [$kop, 'footer']) }}" alt="Footer {{ $kop->name }}" class="w-full rounded-control border border-border">@endif
                    </div>
                @empty
                    <p class="mb-3 text-sm text-text-muted">Belum ada kop. Surat memakai nama pesantren sebagai kepala surat.</p>
                @endforelse
                <form wire:submit="saveLetterhead" class="flex flex-col gap-3 border-t border-border pt-3">
                    <x-ui.input label="Nama kop" name="kop_name" required help="Contoh: Kop resmi, Kop panitia." wire:model="kop_name" />
                    <div class="flex flex-col gap-1">
                        <x-ui.file-drop label="Kop surat (atas)" name="kop_header" accept="image/png,image/jpeg" hint="PNG atau JPG selebar kertas, paling besar 2 MB." wire:model="kop_header" />
                    </div>
                    <div class="flex flex-col gap-1">
                        <x-ui.file-drop label="Footer surat (bawah)" name="kop_footer" accept="image/png,image/jpeg" hint="PNG atau JPG selebar kertas, paling besar 2 MB." wire:model="kop_footer" />
                    </div>
                    <x-ui.button type="submit" icon="upload" wire:loading.attr="disabled" wire:target="saveLetterhead,kop_header,kop_footer">Simpan Kop</x-ui.button>
                </form>
            </x-ui.card>
        </div>
    @endif

    <x-ui.modal name="form-template" :title="$editingTemplate ? 'Ubah template' : 'Template baru'">
        <div class="flex flex-col gap-3">
            <div class="grid gap-3 md:grid-cols-[minmax(0,1fr)_8rem]">
                <x-ui.input label="Nama" name="t_name" required wire:model="t_name" />
                <x-ui.input label="Kode" name="t_code" required help="Masuk ke nomor." wire:model="t_code" />
            </div>
            <x-ui.input label="Perihal" name="t_subject" required wire:model="t_subject" />
            <x-ui.textarea label="Isi" name="t_body" rows="10" required wire:model="t_body" />
            <x-ui.textarea label="Isian tambahan" name="t_fields" rows="3" help="Satu per baris, misalnya: Tanggal acara. Di isi surat ditulis {tanggal_acara}." wire:model="t_fields" />
            <label class="flex min-h-11 items-center gap-3 text-base"><input type="checkbox" wire:model="t_needs_student" class="size-5 accent-primary">Surat ini untuk satu santri</label>
            <details class="rounded-control bg-bg p-3 text-sm">
                <summary class="min-h-11 cursor-pointer content-center font-semibold">Isian otomatis yang tersedia</summary>
                <ul class="mt-2 grid gap-1 md:grid-cols-2">
                    @foreach ($builtIn as $key => $label)
                        <li><code class="font-tabular">{{ '{'.$key.'}' }}</code> {{ $label }}</li>
                    @endforeach
                </ul>
            </details>
        </div>
        <x-slot:footer>
            <x-ui.button variant="secondary" x-on:click="open = false">Batal</x-ui.button>
            <x-ui.button wire:click="saveTemplate" wire:loading.attr="disabled" wire:target="saveTemplate">Simpan Template</x-ui.button>
        </x-slot:footer>
    </x-ui.modal>

    <x-ui.modal name="batal-surat" title="Batalkan surat?">
        <p class="mb-3 text-base">Nomornya tetap tercatat di arsip dan tidak dipakai ulang.</p>
        <x-ui.input label="Alasan" name="voidReason" required wire:model="voidReason" />
        <x-slot:footer>
            <x-ui.button variant="secondary" x-on:click="open = false">Tidak Jadi</x-ui.button>
            <x-ui.button variant="danger" wire:click="confirmVoid">Batalkan Surat</x-ui.button>
        </x-slot:footer>
    </x-ui.modal>
</div>
