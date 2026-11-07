<div class="flex flex-col gap-4">
    @include('wallet.partials.card-nav')

    <x-ui.card>
        <x-ui.page-toolbar>
            <div class="grid min-w-0 flex-1 gap-3 md:grid-cols-[minmax(0,1fr)_200px]">
                <x-ui.input label="Cari nama atau NIS" name="search" type="search" autocomplete="off" wire:model.live.debounce.250ms="search" />
                <x-ui.select label="Status" name="status" placeholder="Semua status" :options="['active' => 'Aktif', 'blocked' => 'Diblokir', 'lost' => 'Hilang']" wire:model.live="status" />
            </div>
            @can('cards.manage')
                <div class="flex flex-wrap gap-2">
                    @if ($selected !== [])
                        <x-ui.button variant="secondary" icon="printer" :href="route('staff.cards.print', ['kartu' => implode(',', $selected)])">Cetak {{ count($selected) }} Kartu</x-ui.button>
                    @endif
                    <x-ui.button icon="plus" x-data x-on:click="$dispatch('open-modal', 'terbitkan-kartu')">Terbitkan Kartu</x-ui.button>
                </div>
            @endcan
        </x-ui.page-toolbar>

        @if ($cards->isEmpty())
            <x-ui.empty-state icon="id-card" message="Belum ada kartu untuk filter ini." class="py-2" />
        @else
            <x-ui.table caption="Daftar kartu santri">
                <thead>
                    <tr>
                        @can('cards.manage')<x-ui.th><span class="sr-only">Pilih untuk cetak</span></x-ui.th>@endcan
                        <x-ui.th>Santri</x-ui.th><x-ui.th>UID</x-ui.th><x-ui.th>PIN</x-ui.th><x-ui.th>Status</x-ui.th>
                        @can('cards.manage')<x-ui.th>Aksi</x-ui.th>@endcan
                    </tr>
                </thead>
                <tbody>
                    @foreach ($cards as $card)
                        <tr wire:key="kartu-{{ $card->uuid }}">
                            @can('cards.manage')
                                <x-ui.td label="Cetak">
                                    @if ($card->status === 'active')
                                        <label class="inline-flex size-11 items-center justify-center">
                                            <input type="checkbox" value="{{ $card->uuid }}" wire:model.live="selected" class="size-5 accent-primary" aria-label="Pilih kartu {{ $card->student->name }}">
                                        </label>
                                    @endif
                                </x-ui.td>
                            @endcan
                            <x-ui.td label="Santri">
                                <a href="{{ route('staff.students.show', $card->student) }}" wire:navigate class="font-semibold text-primary hover:underline">{{ $card->student->name }}</a>
                                <span class="block text-sm text-text-muted">{{ $card->student->nis }}{{ $card->student->classroom ? ' · '.$card->student->classroom->name : '' }}</span>
                            </x-ui.td>
                            <x-ui.td label="UID"><span class="font-tabular">{{ $card->card_uid ? '••••'.substr($card->card_uid, -4) : 'Belum ada' }}</span></x-ui.td>
                            <x-ui.td label="PIN">{{ $card->pin_hash ? 'Sudah dibuat' : 'Belum' }}</x-ui.td>
                            <x-ui.td label="Status">
                                @if ($card->status === 'lost')
                                    <span class="inline-flex items-center gap-1 rounded-full bg-danger-soft px-2.5 py-0.5 text-sm font-semibold text-danger"><x-ui.icon name="circle-alert" size="sm" />Hilang</span>
                                @else
                                    <x-ui.status-badge :status="$card->status" />
                                @endif
                                @if ($card->blocked_reason)<span class="block text-xs text-text-muted">{{ $card->blocked_reason }}</span>@endif
                            </x-ui.td>
                            @can('cards.manage')
                                <x-ui.td label="Aksi">
                                    <div class="flex flex-wrap gap-1">
                                        @if ($card->status === 'active')
                                            <x-ui.button variant="tertiary" wire:click="openBlock('{{ $card->uuid }}')">Blokir<span class="sr-only"> kartu {{ $card->student->name }}</span></x-ui.button>
                                            <x-ui.button variant="tertiary" wire:click="openReplace('{{ $card->uuid }}')">Ganti<span class="sr-only"> kartu {{ $card->student->name }}</span></x-ui.button>
                                            @if ($card->pin_hash)
                                                <x-ui.button variant="tertiary" wire:click="resetPin('{{ $card->uuid }}')" wire:confirm="Hapus PIN kartu ini? Belanja di atas ambang ditolak sampai wali membuat PIN baru.">Reset PIN</x-ui.button>
                                            @endif
                                        @elseif ($card->status === 'blocked')
                                            <x-ui.button variant="tertiary" wire:click="unblock('{{ $card->uuid }}')" wire:confirm="Buka blokir kartu ini?">Buka Blokir</x-ui.button>
                                        @else
                                            -
                                        @endif
                                    </div>
                                </x-ui.td>
                            @endcan
                        </tr>
                    @endforeach
                </tbody>
            </x-ui.table>
            <div class="mt-4">{{ $cards->links() }}</div>
        @endif
    </x-ui.card>

    @can('cards.manage')
        <x-ui.modal name="terbitkan-kartu" title="Terbitkan kartu">
            <div class="flex flex-col gap-3">
                @if ($issueStudentModel)
                    <p class="text-base">Untuk <strong>{{ $issueStudentModel->name }}</strong> <span class="font-tabular text-sm text-text-muted">{{ $issueStudentModel->nis }}</span></p>
                @else
                    <div>
                        <x-ui.input label="Cari santri" name="issueSearch" type="search" autocomplete="off" wire:model.live.debounce.250ms="issueSearch" />
                        @foreach ($issueResults as $result)
                            <button type="button" wire:key="terbit-{{ $result->uuid }}" wire:click="pickIssueStudent('{{ $result->uuid }}')" class="mt-1 flex min-h-11 w-full items-center justify-between gap-2 rounded-control px-2 text-left hover:bg-primary-soft">
                                <span class="font-semibold">{{ $result->name }}</span><span class="font-tabular text-sm text-text-muted">{{ $result->nis }}</span>
                            </button>
                        @endforeach
                    </div>
                @endif
                <div x-data x-on:focus-uid.window="$nextTick(() => $el.querySelector('input')?.focus())">
                    <x-ui.input label="UID kartu" name="issueUid" autocomplete="off" help="Tempelkan kartu ke reader, atau kosongkan bila santri memakai QR di HP dulu." wire:model="issueUid" wire:keydown.enter.prevent="issue" />
                </div>
            </div>
            <x-slot:footer>
                <x-ui.button variant="secondary" x-on:click="open = false">Batal</x-ui.button>
                <x-ui.button wire:click="issue" wire:loading.attr="disabled" wire:target="issue">Terbitkan</x-ui.button>
            </x-slot:footer>
        </x-ui.modal>

        <x-ui.modal name="blokir-kartu" title="Blokir kartu">
            <div class="flex flex-col gap-3">
                <x-ui.select label="Jenis" name="blockKind" :options="['blocked' => 'Blokir sementara', 'lost' => 'Kartu hilang (harus diganti)']" wire:model="blockKind" />
                <x-ui.input label="Alasan" name="blockReason" required wire:model="blockReason" />
            </div>
            <x-slot:footer>
                <x-ui.button variant="secondary" x-on:click="open = false">Batal</x-ui.button>
                <x-ui.button variant="danger" wire:click="block" wire:loading.attr="disabled" wire:target="block">Blokir Kartu</x-ui.button>
            </x-slot:footer>
        </x-ui.modal>

        <x-ui.modal name="ganti-kartu" title="Ganti kartu">
            <p class="mb-3 text-base">Kartu lama langsung tidak berlaku. Saldo dan PIN tetap.</p>
            <x-ui.input label="UID kartu baru" name="replaceUid" autocomplete="off" help="Boleh dikosongkan bila kartu fisik belum ada." wire:model="replaceUid" wire:keydown.enter.prevent="replace" />
            <x-slot:footer>
                <x-ui.button variant="secondary" x-on:click="open = false">Batal</x-ui.button>
                <x-ui.button wire:click="replace" wire:loading.attr="disabled" wire:target="replace">Ganti Kartu</x-ui.button>
            </x-slot:footer>
        </x-ui.modal>
    @endcan
</div>
