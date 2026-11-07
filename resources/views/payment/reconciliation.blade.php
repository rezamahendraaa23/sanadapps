@php use App\Modules\Finance\Support\Rupiah; @endphp

<div>
    <x-ui.page-toolbar>
        <div class="w-full md:max-w-xs">
            <x-ui.select label="Tampilkan" name="filter" :options="['perlu' => 'Perlu perhatian', 'semua' => 'Semua pembayaran online']" wire:model.live="filter" />
        </div>
        @if ($canAct)
            <x-ui.button variant="secondary" icon="refresh-cw" wire:click="syncAll" wire:loading.attr="disabled" wire:target="syncAll">Sinkron yang Menggantung</x-ui.button>
        @endif
    </x-ui.page-toolbar>

    <div class="flex flex-col gap-3">
        @forelse ($payments as $payment)
            <x-ui.card wire:key="rekon-{{ $payment->uuid }}">
                <div class="flex flex-wrap items-start justify-between gap-2">
                    <div class="min-w-0">
                        <a href="{{ route('staff.payments.show', $payment) }}" wire:navigate class="font-tabular font-semibold text-primary hover:underline">{{ $payment->payment_no }}</a>
                        <p class="text-sm text-text-muted">{{ $payment->studentNames() }}{{ $payment->channel ? ' · '.$payment->channel : '' }}</p>
                        <p class="font-tabular text-base font-semibold">{{ Rupiah::format($payment->gross_amount) }}</p>
                    </div>
                    <x-ui.payment-status :status="$payment->status" method="midtrans" />
                </div>
                @if ($payment->needs_review)
                    <x-ui.banner tone="warning" class="mt-3">{{ $payment->review_note }}</x-ui.banner>
                @endif
                <div class="mt-3 flex flex-col gap-2 md:flex-row md:items-end md:justify-between">
                    <p class="text-sm text-text-muted">{{ $payment->last_synced_at ? 'Disinkron '.$payment->last_synced_at->setTimezone(config('sanad.display_timezone'))->translatedFormat('j M, H.i') : 'Belum pernah disinkron' }}</p>
                    @if ($canAct)
                        <x-ui.button variant="secondary" icon="refresh-cw" wire:click="sync('{{ $payment->uuid }}')" wire:loading.attr="disabled" wire:target="sync('{{ $payment->uuid }}')">Sinkron<span class="sr-only"> {{ $payment->payment_no }}</span></x-ui.button>
                    @endif
                </div>
                @if ($payment->needs_review && $canAct)
                    <form wire:submit="resolve('{{ $payment->uuid }}')" class="mt-3 flex flex-col gap-2 border-t border-border pt-3">
                        <x-ui.input :id="'catatan-'.$payment->uuid" label="Tindakan yang sudah dilakukan" name="resolveNote" wire:model="resolveNote" />
                        <x-ui.button type="submit" variant="secondary" class="self-end">Tandai Selesai</x-ui.button>
                    </form>
                @endif
            </x-ui.card>
        @empty
            <x-ui.card><x-ui.empty-state message="Tidak ada pembayaran online yang perlu diperhatikan." /></x-ui.card>
        @endforelse
        <div>{{ $payments->links() }}</div>
    </div>
</div>
