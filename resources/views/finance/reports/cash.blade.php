@php use App\Modules\Finance\Support\Rupiah; @endphp

<div class="flex flex-col gap-4">
    <x-ui.page-toolbar class="!mb-0">
        <div class="grid w-full gap-3 md:max-w-xl md:grid-cols-2">
            <x-ui.input label="Dari" name="from" type="date" wire:model.live="from" />
            <x-ui.input label="Sampai" name="to" type="date" wire:model.live="to" />
        </div>
        <div class="flex flex-wrap gap-2">
            <x-ui.button variant="secondary" icon="file-down" :href="route('staff.cash.export', ['dari' => $from, 'sampai' => $to])">Ekspor</x-ui.button>
            @can('finance.manage')
                <x-ui.button icon="plus" wire:click="openForm">Catat Kas</x-ui.button>
            @endcan
        </div>
    </x-ui.page-toolbar>

    <div class="grid gap-4 md:grid-cols-3">
        <x-ui.card><p class="text-sm text-text-muted">Kas masuk</p><p class="font-display font-tabular text-2xl font-semibold">{{ Rupiah::format($totalIn) }}</p></x-ui.card>
        <x-ui.card><p class="text-sm text-text-muted">Kas keluar</p><p class="font-display font-tabular text-2xl font-semibold">{{ Rupiah::format($totalOut) }}</p></x-ui.card>
        <x-ui.hero-card label="Selisih periode ini" :value="Rupiah::format($totalIn - $totalOut)" />
    </div>

    <x-ui.card>
        @if ($transactions->isEmpty())
            <x-ui.empty-state icon="landmark" message="Belum ada catatan kas pada rentang ini." />
        @else
            <x-ui.table caption="Catatan kas">
                <thead><tr><x-ui.th>Tanggal</x-ui.th><x-ui.th>Kategori</x-ui.th><x-ui.th>Keterangan</x-ui.th><x-ui.th numeric>Masuk</x-ui.th><x-ui.th numeric>Keluar</x-ui.th></tr></thead>
                <tbody>
                    @foreach ($transactions as $transaction)
                        <tr wire:key="kas-{{ $transaction->uuid }}">
                            <x-ui.td label="Tanggal">{{ $transaction->transaction_date->translatedFormat('j M Y') }}</x-ui.td>
                            <x-ui.td label="Kategori">{{ $transaction->category->name }}</x-ui.td>
                            <x-ui.td label="Keterangan">{{ $transaction->description }}</x-ui.td>
                            <x-ui.td label="Masuk" numeric>{{ $transaction->type === 'in' ? Rupiah::format($transaction->amount) : '-' }}</x-ui.td>
                            <x-ui.td label="Keluar" numeric>{{ $transaction->type === 'out' ? Rupiah::format($transaction->amount) : '-' }}</x-ui.td>
                        </tr>
                    @endforeach
                </tbody>
            </x-ui.table>
            <div class="mt-4">{{ $transactions->links() }}</div>
        @endif
    </x-ui.card>

    <x-ui.modal name="form-kas" title="Catat kas">
        <form id="form-kas" wire:submit="save" class="flex flex-col gap-4">
            <x-ui.select label="Jenis" name="form_type" required :options="['out' => 'Kas keluar', 'in' => 'Kas masuk']" wire:model.live="form_type" />
            <x-ui.select label="Kategori" name="form_category" required placeholder="Pilih kategori" :options="$categories" wire:model="form_category" />
            <x-ui.input-rupiah label="Nominal" name="form_amount" required wire:model="form_amount" />
            <x-ui.input label="Tanggal" name="form_date" type="date" required wire:model="form_date" />
            <x-ui.textarea label="Keterangan" name="form_description" required wire:model="form_description" />
            <div class="flex flex-col gap-1">
                <label for="field-form_attachment" class="text-sm font-semibold">Lampiran (JPG, PNG, atau PDF, maksimal 5 MB)</label>
                <input id="field-form_attachment" type="file" accept=".jpg,.jpeg,.png,.pdf" wire:model="form_attachment" class="min-h-11 w-full min-w-0 text-sm">
                @error('form_attachment')
                    <p role="alert" class="flex items-start gap-1 text-sm text-danger"><x-ui.icon name="circle-alert" size="sm" class="mt-0.5" />{{ $message }}</p>
                @enderror
            </div>
        </form>
        <x-slot:footer>
            <x-ui.button variant="secondary" x-on:click="open = false">Batal</x-ui.button>
            <x-ui.button type="submit" form="form-kas" wire:loading.attr="disabled" wire:target="save,form_attachment">Simpan</x-ui.button>
        </x-slot:footer>
    </x-ui.modal>
</div>
