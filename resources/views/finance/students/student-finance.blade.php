@php use App\Modules\Finance\Support\Rupiah; @endphp

<div class="flex flex-col gap-4">
    <x-ui.card title="Tagihan">
        @if ($invoices->isEmpty())
            <x-ui.empty-state icon="receipt-text" message="Belum ada tagihan untuk santri ini." class="py-2" />
        @else
            <x-ui.table caption="Tagihan santri">
                <thead><tr><x-ui.th>Nomor</x-ui.th><x-ui.th numeric>Total</x-ui.th><x-ui.th numeric>Sisa</x-ui.th><x-ui.th>Status</x-ui.th></tr></thead>
                <tbody>
                    @foreach ($invoices as $invoice)
                        <tr wire:key="inv-{{ $invoice->uuid }}">
                            <x-ui.td label="Nomor"><a href="{{ route('staff.invoices.show', $invoice) }}" class="font-tabular inline-flex min-h-11 items-center font-semibold text-primary hover:underline">{{ $invoice->invoice_no }}</a></x-ui.td>
                            <x-ui.td label="Total" numeric>{{ Rupiah::format($invoice->total) }}</x-ui.td>
                            <x-ui.td label="Sisa" numeric>{{ Rupiah::format($invoice->status === 'void' ? 0 : $invoice->outstanding()) }}</x-ui.td>
                            <x-ui.td label="Status"><x-ui.invoice-status :status="$invoice->status" /></x-ui.td>
                        </tr>
                    @endforeach
                </tbody>
            </x-ui.table>
        @endif
    </x-ui.card>

    <x-ui.card title="Pembayaran">
        @forelse ($payments as $payment)
            <div wire:key="pay-{{ $payment->uuid }}" class="flex flex-wrap items-center justify-between gap-2 border-b border-border py-1 last:border-b-0">
                <a href="{{ route('staff.payments.show', $payment) }}" class="font-tabular inline-flex min-h-11 items-center font-semibold text-primary hover:underline">{{ $payment->payment_no }}</a>
                <span class="font-tabular">{{ Rupiah::format($payment->amount) }}</span>
                <x-ui.payment-status :status="$payment->status" />
            </div>
        @empty
            <x-ui.empty-state icon="banknote" message="Belum ada pembayaran." class="py-2" />
        @endforelse
    </x-ui.card>

    <x-ui.card title="Penugasan tagihan">
        @if ($canManage)
            <x-slot:actions>
                <x-ui.button variant="secondary" icon="plus" x-data x-on:click="$dispatch('open-modal', 'form-penugasan')">Tambah Penugasan</x-ui.button>
            </x-slot:actions>
        @endif
        @forelse ($assignments as $assignment)
            <div wire:key="assign-{{ $assignment->uuid }}" class="flex flex-wrap items-center justify-between gap-2 border-b border-border py-2 last:border-b-0">
                <div>
                    <p class="font-semibold">{{ $assignment->feeType->name }}: {{ $assignment->is_active ? ($assignment->custom_amount !== null ? Rupiah::format($assignment->custom_amount) : 'ikut tarif berlaku') : 'tidak ikut' }}</p>
                    <p class="text-sm text-text-muted">Mulai {{ $assignment->start_month->translatedFormat('M Y') }}{{ $assignment->end_month ? ' sampai '.$assignment->end_month->translatedFormat('j M Y') : '' }}</p>
                </div>
                @if ($canManage && ! $assignment->end_month)
                    <x-ui.button variant="tertiary" wire:click="endAssignment('{{ $assignment->uuid }}')">Akhiri</x-ui.button>
                @endif
            </div>
        @empty
            <p class="text-base text-text-muted">Mengikuti aturan umum. Tambahkan penugasan untuk jenis opsional, tarif khusus, atau pengecualian.</p>
        @endforelse
    </x-ui.card>

    <x-ui.card title="Diskon dan beasiswa">
        @if ($canManage)
            <x-slot:actions>
                <x-ui.button variant="secondary" icon="plus" x-data x-on:click="$dispatch('open-modal', 'form-diskon')">Tambah Diskon</x-ui.button>
            </x-slot:actions>
        @endif
        @forelse ($discounts as $discount)
            <div wire:key="disc-{{ $discount->uuid }}" class="border-b border-border py-2 last:border-b-0">
                <p class="font-semibold">{{ $discount->type === 'percent' ? $discount->value.'%' : Rupiah::format($discount->value) }} untuk {{ $discount->feeType?->name ?? 'semua jenis' }}</p>
                <p class="text-sm text-text-muted">{{ $discount->reason }}. Mulai {{ $discount->valid_from->translatedFormat('j M Y') }}{{ $discount->valid_to ? ' sampai '.$discount->valid_to->translatedFormat('j M Y') : '' }}</p>
            </div>
        @empty
            <p class="text-base text-text-muted">Belum ada diskon.</p>
        @endforelse
    </x-ui.card>

    @if ($canManage)
        <x-ui.modal name="form-penugasan" title="Tambah penugasan tagihan">
            <form id="form-penugasan" wire:submit="saveAssignment" class="flex flex-col gap-4">
                <x-ui.select label="Jenis tagihan" name="assign_fee" required placeholder="Pilih jenis" :options="$feeTypes" wire:model="assign_fee" />
                <x-ui.input-rupiah label="Nominal khusus" name="assign_amount" help="Kosongkan agar mengikuti tarif yang berlaku." wire:model="assign_amount" />
                <x-ui.input label="Mulai bulan" name="assign_start" type="date" required wire:model="assign_start" />
                <label class="flex min-h-11 items-center gap-3 text-base"><input type="checkbox" wire:model="assign_active" class="size-5 accent-primary">Ikut jenis tagihan ini</label>
                <p class="text-sm text-text-muted">Hilangkan centang untuk mengecualikan, misalnya santri yang tidak makan di pondok.</p>
            </form>
            <x-slot:footer>
                <x-ui.button variant="secondary" x-on:click="open = false">Batal</x-ui.button>
                <x-ui.button type="submit" form="form-penugasan" wire:loading.attr="disabled" wire:target="saveAssignment">Simpan</x-ui.button>
            </x-slot:footer>
        </x-ui.modal>

        <x-ui.modal name="form-diskon" title="Tambah diskon">
            <form id="form-diskon" wire:submit="saveDiscount" class="flex flex-col gap-4">
                <x-ui.select label="Berlaku untuk" name="discount_fee" placeholder="Semua jenis tagihan" :options="$feeTypes" wire:model="discount_fee" />
                <div class="grid gap-4 md:grid-cols-2">
                    <x-ui.select label="Bentuk" name="discount_type" required :options="['percent' => 'Persen', 'fixed' => 'Nominal']" wire:model="discount_type" />
                    <x-ui.input label="Nilai" name="discount_value" inputmode="numeric" required wire:model="discount_value" />
                </div>
                <x-ui.input label="Alasan" name="discount_reason" required help="Contoh: yatim, prestasi." wire:model="discount_reason" />
                <div class="grid gap-4 md:grid-cols-2">
                    <x-ui.input label="Berlaku mulai" name="discount_from" type="date" required wire:model="discount_from" />
                    <x-ui.input label="Berlaku sampai" name="discount_to" type="date" wire:model="discount_to" />
                </div>
            </form>
            <x-slot:footer>
                <x-ui.button variant="secondary" x-on:click="open = false">Batal</x-ui.button>
                <x-ui.button type="submit" form="form-diskon" wire:loading.attr="disabled" wire:target="saveDiscount">Simpan</x-ui.button>
            </x-slot:footer>
        </x-ui.modal>
    @endif
</div>
