<div class="mx-auto flex w-full max-w-[720px] flex-col gap-4">
    <x-ui.card title="Proses kenaikan kelas">
        <div class="grid gap-4 md:grid-cols-2">
            <x-ui.select label="Kelas asal" name="from" required placeholder="Pilih kelas" :options="$classrooms" wire:model.live="from" />
            <x-ui.select label="Tujuan" name="to" required placeholder="Pilih tujuan" :options="$targets" wire:model.live="to" />
        </div>

        @if ($preview !== null)
            <div class="mt-4">
                @if ($preview->isEmpty())
                    <x-ui.empty-state icon="users" message="Tidak ada santri aktif di kelas ini." class="py-2" />
                @else
                    <h3 class="text-base font-semibold">Pratinjau: {{ $preview->count() }} santri{{ $targetName ? ' menuju '.$targetName : '' }}</h3>
                    <ul class="mt-2 max-h-60 overflow-y-auto rounded-control border border-border p-3 text-sm">
                        @foreach ($preview as $student)
                            <li class="py-0.5">{{ $student->name }} <span class="font-tabular text-text-muted">{{ $student->nis }}</span></li>
                        @endforeach
                    </ul>
                    @error('to')
                        <p role="alert" class="mt-2 flex items-start gap-1 text-sm text-danger"><x-ui.icon name="circle-alert" size="sm" class="mt-0.5" />{{ $message }}</p>
                    @enderror
                    <div class="mt-4 flex justify-end">
                        <x-ui.button class="w-full md:w-auto" :disabled="! $targetName" x-data x-on:click="$dispatch('open-modal', 'konfirmasi-kenaikan')">Proses {{ $preview->count() }} Santri</x-ui.button>
                    </div>
                @endif
            </div>
        @endif
    </x-ui.card>

    <x-ui.card title="Bisa dibatalkan">
        @forelse ($batches as $batch)
            <div wire:key="batch-{{ $batch->batch_id }}-{{ $batch->classroom_id }}" class="flex flex-wrap items-center justify-between gap-2 border-b border-border py-2 last:border-b-0">
                <p class="text-base">{{ $classrooms[$batch->classroom_id] ?? 'Kelas dihapus' }}: {{ $batch->total }} santri {{ $batch->status === 'graduated' ? 'diluluskan' : 'dinaikkan' }}</p>
                <x-ui.button variant="secondary" wire:click="undo('{{ $batch->batch_id }}')" wire:confirm="Kembalikan {{ $batch->total }} santri ke kelas semula?">Batalkan</x-ui.button>
            </div>
        @empty
            <x-ui.empty-state icon="clock" message="Belum ada proses dalam 24 jam terakhir." class="py-2" />
        @endforelse
    </x-ui.card>

    <x-ui.modal name="konfirmasi-kenaikan" title="Proses kenaikan kelas?">
        <p>{{ $preview?->count() }} santri dari {{ $classrooms[(int) $from] ?? '' }} akan {{ $to === 'lulus' ? 'diluluskan' : 'dipindahkan ke '.$targetName }}. Proses ini bisa dibatalkan dalam 24 jam.</p>
        <x-slot:footer>
            <x-ui.button variant="secondary" x-on:click="open = false">Batal</x-ui.button>
            <x-ui.button wire:click="execute" wire:loading.attr="disabled" wire:target="execute">Proses</x-ui.button>
        </x-slot:footer>
    </x-ui.modal>
</div>
