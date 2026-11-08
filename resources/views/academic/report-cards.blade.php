<div class="flex flex-col gap-4">
    <x-ui.card>
        <x-ui.page-toolbar class="!mb-0">
            <div class="grid min-w-0 flex-1 gap-3 md:max-w-xl md:grid-cols-2">
                <x-ui.select label="Periode" name="termId" placeholder="Pilih" :options="$terms" wire:model.live="termId" />
                <x-ui.select label="Kelas" name="classroomId" placeholder="Pilih" :options="$classrooms" wire:model.live="classroomId" />
            </div>
            <div class="flex flex-wrap gap-2">
                <x-ui.button variant="secondary" wire:click="generate" wire:loading.attr="disabled" wire:target="generate">
                    <span wire:loading.remove wire:target="generate">Buat Draf Rapor</span><span wire:loading wire:target="generate">Menghitung…</span>
                </x-ui.button>
                @if ($draftCount > 0)
                    <x-ui.button x-data x-on:click="$dispatch('open-modal', 'terbitkan-rapor')">Terbitkan {{ $draftCount }} Rapor</x-ui.button>
                @endif
            </div>
        </x-ui.page-toolbar>
    </x-ui.card>

    <x-ui.card>
        @if ($cards->isEmpty())
            <x-ui.empty-state icon="file-text" message="Pilih periode dan kelas, lalu buat draf rapor." class="py-2" />
        @else
            <x-ui.table caption="Rapor per santri">
                <thead><tr><x-ui.th>Santri</x-ui.th><x-ui.th numeric>Rata-rata</x-ui.th><x-ui.th>Status</x-ui.th><x-ui.th>Aksi</x-ui.th></tr></thead>
                <tbody>
                    @foreach ($cards as $card)
                        <tr wire:key="r-{{ $card->uuid }}">
                            <x-ui.td label="Santri"><span class="break-words">{{ $card->student->name }}</span><span class="font-tabular block text-sm text-text-muted">{{ $card->student->nis }}</span></x-ui.td>
                            <x-ui.td label="Rata-rata" class="text-right"><span class="font-tabular">{{ $card->items_avg_final_score !== null ? number_format((float) $card->items_avg_final_score, 1, ',', '') : '-' }}</span></x-ui.td>
                            <x-ui.td label="Status">
                                @if ($card->status === 'published')
                                    <span class="inline-flex items-center gap-1 text-sm font-semibold text-success"><x-ui.icon name="lock" size="sm" />Terbit</span>
                                @else
                                    <span class="text-sm text-text-muted">Draf</span>
                                @endif
                            </x-ui.td>
                            <x-ui.td label="Aksi">
                                <div class="flex flex-wrap gap-1">
                                    <x-ui.button variant="tertiary" icon="file-down" :href="route('report-cards.pdf', $card)">PDF<span class="sr-only"> {{ $card->student->name }}</span></x-ui.button>
                                    @if ($card->status === 'draft')
                                        <x-ui.button variant="tertiary" wire:click="editNote('{{ $card->uuid }}')">Catatan<span class="sr-only"> {{ $card->student->name }}</span></x-ui.button>
                                    @endif
                                </div>
                            </x-ui.td>
                        </tr>
                    @endforeach
                </tbody>
            </x-ui.table>
        @endif
    </x-ui.card>

    <x-ui.modal name="terbitkan-rapor" title="Terbitkan {{ $draftCount }} rapor?">
        <p class="text-base">Wali langsung bisa melihat dan menerima kabar. Nilai periode ini terkunci; mengubahnya perlu alasan dan tercatat.</p>
        <x-slot:footer>
            <x-ui.button variant="secondary" x-on:click="open = false">Batal</x-ui.button>
            <x-ui.button wire:click="publish" wire:loading.attr="disabled" wire:target="publish">Terbitkan</x-ui.button>
        </x-slot:footer>
    </x-ui.modal>

    <x-ui.modal name="catatan-rapor" title="Catatan wali kelas">
        <x-ui.textarea label="Catatan" name="note" rows="4" wire:model="note" />
        <x-slot:footer>
            <x-ui.button variant="secondary" x-on:click="open = false">Batal</x-ui.button>
            <x-ui.button wire:click="saveNote" wire:loading.attr="disabled" wire:target="saveNote">Simpan</x-ui.button>
        </x-slot:footer>
    </x-ui.modal>
</div>
