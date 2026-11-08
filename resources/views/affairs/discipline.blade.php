<div class="flex flex-col gap-4">
    @if ($canRecord)
        <x-ui.card title="Catat pelanggaran">
            <form wire:submit="record" class="flex flex-col gap-3">
                @if ($student)
                    <p class="flex flex-wrap items-center justify-between gap-2 rounded-control bg-primary-soft p-3 text-base"><strong class="min-w-0 break-words">{{ $student->name }}</strong><button type="button" wire:click="$set('studentUuid', '')" class="inline-flex min-h-11 items-center text-sm font-semibold text-primary underline">Ganti</button></p>
                @else
                    <x-ui.input label="Cari santri" name="search" type="search" autocomplete="off" wire:model.live.debounce.250ms="search" />
                    @foreach ($results as $result)
                        <button type="button" wire:key="s-{{ $result->uuid }}" wire:click="chooseStudent('{{ $result->uuid }}')" class="flex min-h-11 w-full items-center justify-between gap-2 rounded-control px-2 text-left hover:bg-primary-soft"><span class="font-semibold break-words">{{ $result->name }}</span><span class="font-tabular shrink-0 text-sm text-text-muted">{{ $result->nis }}</span></button>
                    @endforeach
                @endif
                @if ($ruleOptions === [])
                    <x-ui.banner tone="warning">Belum ada aturan pelanggaran. Admin perlu menambahkannya dulu.</x-ui.banner>
                @else
                    <x-ui.select label="Jenis pelanggaran" name="rule_id" required placeholder="Pilih" :options="$ruleOptions" wire:model="rule_id" />
                    <x-ui.input label="Tanggal" name="occurred_on" type="date" required wire:model="occurred_on" />
                    <x-ui.textarea label="Catatan" name="notes" wire:model="notes" />
                    <div class="flex flex-col gap-1">
                        <x-ui.file-drop label="Foto bukti" name="evidence" accept="image/jpeg,image/png" hint="Tidak wajib" capture="environment" wire:model="evidence" />
                    </div>
                    <x-ui.button type="submit" wire:loading.attr="disabled" wire:target="record,evidence">Simpan Catatan</x-ui.button>
                @endif
            </form>
        </x-ui.card>
    @endif

    <div class="grid gap-4 lg:grid-cols-2">
        <x-ui.card title="Poin tertinggi tahun ini" class="min-w-0">
            @forelse ($ranking as $row)
                <div wire:key="r-{{ $row->uuid }}" class="flex justify-between gap-3 border-b border-border py-2 last:border-b-0"><span class="min-w-0 text-base break-words">{{ $row->name }}</span><span class="font-tabular shrink-0 font-semibold">{{ $row->total }}</span></div>
            @empty
                <x-ui.empty-state icon="clipboard-list" message="Belum ada catatan tahun ini." class="py-2" />
            @endforelse
        </x-ui.card>
        <x-ui.card title="Catatan terbaru" class="min-w-0">
            @forelse ($records as $record)
                <div wire:key="c-{{ $record->uuid }}" class="border-b border-border py-2 last:border-b-0">
                    <p class="flex justify-between gap-3"><span class="min-w-0 font-semibold break-words">{{ $record->student->name }}</span><span class="font-tabular shrink-0 text-sm font-semibold">{{ $record->points }} poin</span></p>
                    <p class="text-sm break-words">{{ $record->rule->name }}{{ $record->notes ? ': '.$record->notes : '' }}</p>
                    <p class="text-xs text-text-muted">{{ $record->occurred_on->translatedFormat('j M Y') }}</p>
                </div>
            @empty
                <x-ui.empty-state icon="clipboard-list" message="Belum ada catatan." class="py-2" />
            @endforelse
            <div class="mt-2">{{ $records->links() }}</div>
        </x-ui.card>
    </div>

    @if ($canConfigure)
        <div class="grid gap-4 lg:grid-cols-2">
            <x-ui.card title="Aturan dan poin" class="min-w-0">
                <form wire:submit="saveRule" class="grid gap-3 md:grid-cols-[1fr_120px_90px_auto] md:items-start">
                    <x-ui.input label="Nama aturan" name="rule_name" wire:model="rule_name" />
                    <x-ui.input label="Kategori" name="rule_category" wire:model="rule_category" />
                    <x-ui.input label="Poin" name="rule_points" inputmode="numeric" wire:model="rule_points" />
                    <x-ui.button type="submit" variant="secondary" class="md:mt-6">Tambah</x-ui.button>
                </form>
                @foreach ($rules as $rule)
                    <div wire:key="a-{{ $rule->uuid }}" class="mt-1 flex flex-wrap items-center justify-between gap-2 border-b border-border py-1 last:border-b-0">
                        <span @class(['min-w-0 text-base break-words', 'text-text-muted line-through' => ! $rule->is_active])>{{ $rule->name }} <span class="font-tabular text-sm">{{ $rule->points }} poin</span></span>
                        <x-ui.button variant="tertiary" wire:click="toggleRule('{{ $rule->uuid }}')">{{ $rule->is_active ? 'Nonaktifkan' : 'Aktifkan' }}<span class="sr-only"> {{ $rule->name }}</span></x-ui.button>
                    </div>
                @endforeach
            </x-ui.card>
            <x-ui.card title="Ambang sanksi" class="min-w-0">
                <form wire:submit="saveSanction" class="flex flex-col gap-3">
                    <div class="grid grid-cols-[1fr_110px] gap-3">
                        <x-ui.input label="Nama sanksi" name="sanction_name" help="Contoh: SP1." wire:model="sanction_name" />
                        <x-ui.input label="Ambang poin" name="sanction_points" inputmode="numeric" wire:model="sanction_points" />
                    </div>
                    <x-ui.input label="Tindakan" name="sanction_action" wire:model="sanction_action" />
                    <label class="flex min-h-11 items-center gap-3 text-base"><input type="checkbox" wire:model="sanction_notify" class="size-5 accent-primary">Beri tahu wali</label>
                    <x-ui.button type="submit" variant="secondary" class="self-start">Tambah Ambang</x-ui.button>
                </form>
                @foreach ($sanctions as $sanction)
                    <div wire:key="b-{{ $sanction->uuid }}" class="mt-1 flex items-center justify-between gap-2 border-b border-border py-1 last:border-b-0">
                        <span class="min-w-0 text-base break-words">{{ $sanction->name }} <span class="font-tabular text-sm">mulai {{ $sanction->min_points }} poin</span></span>
                        <x-ui.button variant="tertiary" wire:click="removeSanction('{{ $sanction->uuid }}')" wire:confirm="Hapus ambang {{ $sanction->name }}?">Hapus<span class="sr-only"> {{ $sanction->name }}</span></x-ui.button>
                    </div>
                @endforeach
                <div class="mt-4 flex flex-col gap-2 border-t border-border pt-3">
                    <x-ui.select label="Terlambat kembali dari izin otomatis jadi" name="late_rule" placeholder="Tidak memberi poin" :options="$ruleOptions" wire:model="late_rule" />
                    <x-ui.button variant="secondary" class="self-start" wire:click="saveLateRule">Simpan</x-ui.button>
                </div>
            </x-ui.card>
        </div>
    @endif
</div>
