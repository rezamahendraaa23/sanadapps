@php
    use App\Modules\Finance\Support\Rupiah;
    $tz = config('sanad.display_timezone');
    $uuids = $queue->pluck('uuid')->values();
@endphp

<div
    x-data="{
        order: @js($uuids),
        move(step) {
            const index = this.order.indexOf($wire.current);
            const next = this.order[Math.min(Math.max(index + step, 0), this.order.length - 1)];
            if (next) $wire.select(next);
        },
    }"
    x-on:keydown.window="
        if (['INPUT', 'TEXTAREA', 'SELECT'].includes($event.target.tagName) || document.querySelector('[role=dialog]:not([style*=none])')) return;
        if ($event.key === 'ArrowDown') { $event.preventDefault(); move(1); }
        if ($event.key === 'ArrowUp') { $event.preventDefault(); move(-1); }
        if ($event.key.toLowerCase() === 'v' && $wire.current) $wire.verify();
        if ($event.key.toLowerCase() === 't' && $wire.current) $dispatch('open-modal', 'tolak-bukti');
    "
>
    @if ($queue->isEmpty())
        <x-ui.card><x-ui.empty-state message="Tidak ada yang menunggu. Bukti transfer baru dari wali muncul di sini." /></x-ui.card>
    @else
        <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_minmax(0,2fr)]">
            <x-ui.card>
                <h2 class="mb-2 text-lg font-semibold">Antrean ({{ $queue->count() }})</h2>
                <ul class="flex max-h-[32rem] flex-col gap-1 overflow-y-auto">
                    @foreach ($queue as $payment)
                        <li wire:key="queue-{{ $payment->uuid }}">
                            <button
                                type="button"
                                wire:click="select('{{ $payment->uuid }}')"
                                @if ($selected?->is($payment)) aria-current="true" @endif
                                @class(['flex min-h-11 w-full flex-col rounded-control p-2 text-left hover:bg-primary-soft', 'bg-primary-soft' => $selected?->is($payment)])
                            >
                                <span class="font-semibold">{{ $payment->studentNames() }}</span>
                                <span class="font-tabular text-sm">{{ Rupiah::format($payment->transferAmount()) }}</span>
                                <span class="text-xs text-text-muted">{{ $payment->updated_at->setTimezone($tz)->translatedFormat('j M, H.i') }}</span>
                            </button>
                        </li>
                    @endforeach
                </ul>
                <p class="mt-3 hidden text-xs text-text-muted lg:block">Pintasan: panah atas dan bawah untuk pindah, V verifikasi, T tolak.</p>
            </x-ui.card>

            @if ($selected)
                <x-ui.card wire:key="detail-{{ $selected->uuid }}">
                    <div class="grid gap-4 xl:grid-cols-2">
                        <div class="min-w-0">
                            <h2 class="text-sm font-semibold text-text-muted">Bukti transfer</h2>
                            @if ($selected->proof_path && str_ends_with(strtolower($selected->proof_path), '.pdf'))
                                <x-ui.button variant="secondary" class="mt-2" icon="file-down" :href="route('staff.payments.proof', $selected)" target="_blank">Buka PDF Bukti</x-ui.button>
                            @elseif ($selected->proof_path)
                                <a href="{{ route('staff.payments.proof', $selected) }}" target="_blank" class="mt-2 block" title="Buka ukuran penuh">
                                    <img src="{{ route('staff.payments.proof', $selected) }}" alt="Bukti transfer {{ $selected->payment_no }}" class="max-h-[28rem] w-full rounded-control border border-border object-contain">
                                </a>
                            @endif
                        </div>
                        <div class="flex min-w-0 flex-col gap-3">
                            <dl class="grid grid-cols-[auto_minmax(0,1fr)] gap-x-3 gap-y-1 text-sm">
                                <dt class="text-text-muted">Kwitansi</dt><dd class="font-tabular text-right">{{ $selected->payment_no }}</dd>
                                <dt class="text-text-muted">Rekening tujuan</dt><dd class="text-right">{{ $selected->bankAccount?->bank_name }} {{ $selected->bankAccount?->account_no }}</dd>
                                <dt class="text-text-muted">Tagihan</dt><dd class="font-tabular text-right">{{ Rupiah::format($selected->amount) }}</dd>
                                @if ($selected->unique_code)
                                    <dt class="text-text-muted">Kode unik</dt><dd class="font-tabular text-right">{{ $selected->unique_code }}</dd>
                                @endif
                            </dl>
                            <p class="rounded-control bg-accent-soft p-3 text-center">
                                <span class="block text-sm">Harus masuk di mutasi</span>
                                <span class="font-display font-tabular text-2xl font-semibold">{{ Rupiah::format($selected->transferAmount()) }}</span>
                            </p>
                            <ul class="text-sm">
                                @foreach ($selected->invoices as $invoice)
                                    <li><a href="{{ route('staff.invoices.show', $invoice) }}" wire:navigate class="font-tabular text-primary hover:underline">{{ $invoice->invoice_no }}</a> {{ $invoice->student?->name }}</li>
                                @endforeach
                            </ul>
                            <div class="mt-auto flex flex-col gap-2">
                                <x-ui.button wire:click="verify" wire:loading.attr="disabled" wire:target="verify">Verifikasi</x-ui.button>
                                <x-ui.button variant="secondary" x-data x-on:click="$dispatch('open-modal', 'tolak-bukti')">Tolak</x-ui.button>
                            </div>
                        </div>
                    </div>
                </x-ui.card>
            @endif
        </div>
    @endif

    <x-ui.modal name="tolak-bukti" title="Tolak bukti transfer?">
        <div class="flex flex-col gap-3">
            <p>Wali menerima alasan ini dan bisa mengunggah ulang bukti pada pembayaran yang sama.</p>
            <x-ui.textarea label="Alasan" name="reason" required wire:model="reason" />
        </div>
        <x-slot:footer>
            <x-ui.button variant="secondary" x-on:click="open = false">Batal</x-ui.button>
            <x-ui.button variant="danger" wire:click="reject" wire:loading.attr="disabled" wire:target="reject">Tolak Bukti</x-ui.button>
        </x-slot:footer>
    </x-ui.modal>
</div>
