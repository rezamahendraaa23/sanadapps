@php
    use App\Modules\Finance\Support\Rupiah;
    $tz = config('sanad.display_timezone');
    $methods = ['cash' => 'Tunai', 'manual_transfer' => 'Transfer manual', 'midtrans' => 'Online', 'wallet' => 'Dompet'];
@endphp

<div>
    <x-ui.page-toolbar>
        <div class="grid w-full gap-3 md:max-w-xl md:grid-cols-2">
            <x-ui.select label="Status" name="status" placeholder="Semua status" :options="['pending' => 'Menunggu bukti', 'review' => 'Menunggu verifikasi', 'settlement' => 'Lunas', 'failed' => 'Ditolak', 'refunded' => 'Dikembalikan']" wire:model.live="status" />
            <x-ui.select label="Cara bayar" name="method" placeholder="Semua cara" :options="$methods" wire:model.live="method" />
        </div>
        @can('finance.manage')
            <x-ui.button icon="banknote" :href="route('staff.payments.cash')">Catat Tunai</x-ui.button>
        @endcan
    </x-ui.page-toolbar>

    <x-ui.card>
        @if ($payments->isEmpty())
            <x-ui.empty-state icon="banknote" message="Belum ada pembayaran untuk filter ini." />
        @else
            <x-ui.table caption="Daftar pembayaran">
                <thead><tr><x-ui.th>Kwitansi</x-ui.th><x-ui.th>Santri</x-ui.th><x-ui.th>Cara</x-ui.th><x-ui.th numeric>Nominal</x-ui.th><x-ui.th>Status</x-ui.th><x-ui.th>Waktu</x-ui.th></tr></thead>
                <tbody>
                    @foreach ($payments as $payment)
                        <tr wire:key="payment-{{ $payment->uuid }}" class="hover:bg-primary-soft">
                            <x-ui.td label="Kwitansi"><a href="{{ route('staff.payments.show', $payment) }}" class="font-tabular inline-flex min-h-11 items-center font-semibold text-primary hover:underline">{{ $payment->payment_no }}</a></x-ui.td>
                            <x-ui.td label="Santri">{{ $payment->invoices->pluck('student.name')->filter()->unique()->join(', ') }}</x-ui.td>
                            <x-ui.td label="Cara">{{ $methods[$payment->method] }}</x-ui.td>
                            <x-ui.td label="Nominal" numeric>{{ Rupiah::format($payment->amount) }}</x-ui.td>
                            <x-ui.td label="Status"><x-ui.payment-status :status="$payment->status" /></x-ui.td>
                            <x-ui.td label="Waktu">{{ $payment->created_at->setTimezone($tz)->translatedFormat('j M Y, H.i') }}</x-ui.td>
                        </tr>
                    @endforeach
                </tbody>
            </x-ui.table>
            <div class="mt-4">{{ $payments->links() }}</div>
        @endif
    </x-ui.card>
</div>
