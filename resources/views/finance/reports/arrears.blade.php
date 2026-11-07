@php use App\Modules\Finance\Support\Rupiah; @endphp

<div class="flex flex-col gap-4">
    <x-ui.page-toolbar class="!mb-0">
        <div class="w-full md:max-w-xs">
            <x-ui.select label="Kelas" name="classroom" placeholder="Semua kelas" :options="$classrooms" wire:model.live="classroom" />
        </div>
        <div class="flex flex-wrap gap-2">
            @can('finance.manage')
                <x-ui.button variant="secondary" :href="route('staff.arrears.import')" wire:navigate>Impor Tunggakan Awal</x-ui.button>
                @if ($waEnabled && $rows->isNotEmpty())
                    <x-ui.button icon="message-circle" wire:click="prepareReminder" wire:loading.attr="disabled" wire:target="prepareReminder">Kirim Pengingat WA</x-ui.button>
                @endif
            @endcan
            <x-ui.button variant="secondary" icon="file-down" :href="route('staff.arrears.export', array_filter(['kelas' => $classroom]))">Ekspor</x-ui.button>
        </div>
    </x-ui.page-toolbar>

    <x-ui.card>
        @if ($rows->isEmpty())
            <x-ui.empty-state message="Tidak ada tunggakan untuk pilihan ini." />
        @else
            <p class="mb-3 text-base">{{ $rows->count() }} santri menunggak, total <strong class="font-tabular">{{ Rupiah::format($grandTotal) }}</strong></p>
            <x-ui.table caption="Tunggakan per santri">
                <thead><tr><x-ui.th>Santri</x-ui.th><x-ui.th>Kelas</x-ui.th><x-ui.th numeric>Tagihan</x-ui.th><x-ui.th numeric>Tunggakan</x-ui.th><x-ui.th numeric>Lama</x-ui.th></tr></thead>
                <tbody>
                    @foreach ($rows as $row)
                        <tr wire:key="tunggakan-{{ $row['student']->uuid }}" class="hover:bg-primary-soft">
                            <x-ui.td label="Santri"><a href="{{ route('staff.invoices.index', ['cari' => $row['student']->nis]) }}" wire:navigate class="inline-flex min-h-11 items-center font-semibold text-primary hover:underline">{{ $row['student']->name }}</a></x-ui.td>
                            <x-ui.td label="Kelas">{{ $row['classroom'] ?? '-' }}</x-ui.td>
                            <x-ui.td label="Tagihan" numeric>{{ $row['invoices'] }}</x-ui.td>
                            <x-ui.td label="Tunggakan" numeric>{{ Rupiah::format($row['outstanding']) }}</x-ui.td>
                            <x-ui.td label="Lama" numeric>{{ $row['days'] }} hari</x-ui.td>
                        </tr>
                    @endforeach
                </tbody>
            </x-ui.table>
        @endif
    </x-ui.card>

    <x-ui.modal name="kirim-pengingat" title="Kirim pengingat WhatsApp?">
        @if ($reminderPreview)
            <p class="text-base">{{ $reminderPreview['count'] }} tagihan akan diingatkan ke wali masing-masing. Pesan dikirim bertahap lewat antrean.</p>
            <p class="mt-3 text-sm font-semibold">Contoh pesan</p>
            <div class="mt-1 rounded-control bg-bg p-3">
                <p class="ml-auto max-w-[85%] rounded-control bg-primary-soft p-3 text-sm whitespace-pre-line break-words">{{ $reminderPreview['message'] }}</p>
            </div>
        @endif
        <x-slot:footer>
            <x-ui.button variant="secondary" x-on:click="open = false">Batal</x-ui.button>
            <x-ui.button wire:click="sendReminder" wire:loading.attr="disabled" wire:target="sendReminder">Kirim Pengingat</x-ui.button>
        </x-slot:footer>
    </x-ui.modal>
</div>
