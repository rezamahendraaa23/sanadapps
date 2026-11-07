@php
    use App\Modules\Finance\Support\Rupiah;
    $tz = config('sanad.display_timezone');
    $icons = ['topup' => 'plus', 'spend' => 'store', 'refund' => 'refresh-cw', 'adjustment' => 'scale', 'payout' => 'banknote'];
@endphp

<div class="flex flex-col gap-4">
    @if (! $child)
        <x-ui.card>
            <x-ui.empty-state icon="users" message="Nomor Anda belum terhubung ke data santri. Hubungi admin pesantren." />
        </x-ui.card>
    @else
        @if ($children->count() > 1)
            <nav aria-label="Pilih anak" class="flex gap-2 overflow-x-auto pb-1">
                @foreach ($children as $item)
                    <button type="button" wire:key="anak-{{ $item->uuid }}" wire:click="selectChild('{{ $item->uuid }}')" @if ($item->is($child)) aria-current="true" @endif
                        @class(['inline-flex min-h-11 shrink-0 items-center rounded-full border px-4 text-sm font-semibold', 'border-primary bg-primary-soft text-primary' => $item->is($child), 'border-border' => ! $item->is($child)])>{{ $item->name }}</button>
                @endforeach
            </nav>
        @endif

        <x-ui.hero-card label="Saldo {{ $child->name }}" :value="Rupiah::format($wallet->balance)">
            @if ($wallet->status === 'blocked')
                Belanja sedang dihentikan.
            @elseif ($wallet->daily_limit !== null)
                Limit hari ini {{ Rupiah::format($wallet->daily_limit) }}, terpakai {{ Rupiah::format($spentToday) }}.
            @else
                Tanpa limit harian.
            @endif
            <x-slot:action>
                <x-ui.button variant="on-primary" class="flex-1" :href="route('wali.wallet.topup', ['anak' => $child->uuid])" wire:navigate>Top-up</x-ui.button>
                <x-ui.button variant="on-primary" class="flex-1" x-data x-on:click="$dispatch('open-modal', 'atur-limit')">Atur Limit</x-ui.button>
            </x-slot:action>
        </x-ui.hero-card>

        @foreach ($pending as $payment)
            <a href="{{ route('wali.payments.show', $payment) }}" wire:navigate wire:key="tunggu-{{ $payment->uuid }}" class="hatch flex min-h-11 items-center justify-between gap-2 rounded-card border border-border bg-surface p-4">
                <span class="min-w-0 text-base">Top-up {{ Rupiah::format($payment->amount) }} belum selesai</span>
                <x-ui.icon name="chevron-right" />
            </a>
        @endforeach

        <x-ui.card title="Kartu">
            @if ($card)
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <x-ui.status-badge status="active" />
                    <p class="text-sm text-text-muted">{{ $card->pin_hash ? 'PIN sudah dibuat' : 'PIN belum dibuat' }}</p>
                </div>
                <div class="mt-3 flex flex-col gap-2">
                    <x-ui.button variant="secondary" class="w-full" x-data x-on:click="$dispatch('open-modal', 'atur-pin')">{{ $card->pin_hash ? 'Ganti PIN' : 'Buat PIN' }}</x-ui.button>
                    <x-ui.button variant="tertiary" class="w-full" x-data x-on:click="$dispatch('open-modal', 'blokir-kartu')">Blokir Kartu</x-ui.button>
                </div>
            @elseif ($hasBlockedCard)
                <div class="flex items-start gap-2"><x-ui.status-badge status="blocked" class="shrink-0" /></div>
                <p class="mt-2 text-base">Kartu tidak bisa dipakai. Saldo tetap aman. Hubungi admin pesantren untuk membuka atau mengganti kartu.</p>
            @else
                <x-ui.empty-state icon="id-card" message="Kartu belum diterbitkan pesantren." class="py-2" />
            @endif
        </x-ui.card>

        <x-ui.card title="Riwayat">
            @forelse ($transactions as $transaction)
                @php($isOrder = $transaction->ref_type === 'pos_order')
                <div wire:key="trx-{{ $transaction->uuid }}" class="border-b border-border last:border-b-0">
                    @if ($isOrder)<button type="button" wire:click="openReceipt('{{ $transaction->uuid }}')" class="w-full text-left">@endif
                    <div class="flex min-h-11 items-center gap-3 py-2">
                        <x-ui.icon :name="$icons[$transaction->type]" class="shrink-0 text-text-muted" />
                        <span class="min-w-0 flex-1">
                            <span class="block text-base break-words">{{ $transaction->merchant?->name ?? $transaction->label() }}</span>
                            <span class="block text-xs text-text-muted">{{ $transaction->created_at->setTimezone($tz)->translatedFormat('j M, H.i') }}{{ $isOrder ? ' · '.$transaction->label() : '' }}</span>
                        </span>
                        <span class="shrink-0 text-right">
                            <span @class(['font-tabular block font-semibold', 'text-success' => $transaction->direction === 'credit'])>{{ $transaction->direction === 'credit' ? '+' : '-' }}{{ Rupiah::format($transaction->amount) }}</span>
                            <span class="font-tabular block text-xs text-text-muted">Saldo {{ Rupiah::format($transaction->balance_after) }}</span>
                        </span>
                    </div>
                    @if ($isOrder)</button>@endif
                </div>
            @empty
                <x-ui.empty-state icon="wallet" message="Belum ada transaksi." class="py-2" />
            @endforelse
            @if ($transactions->hasPages())
                <div class="mt-4">{{ $transactions->links() }}</div>
            @endif
        </x-ui.card>

        <x-ui.modal name="atur-limit" title="Atur dompet">
            <div class="flex flex-col gap-3">
                <x-ui.input-rupiah label="Limit harian" name="daily_limit" help="Kosongkan bila tanpa limit." wire:model="daily_limit" />
                <x-ui.input-rupiah label="Limit per transaksi" name="per_transaction_limit" help="Kosongkan bila tanpa limit." wire:model="per_transaction_limit" />
                <label class="flex min-h-11 items-center gap-3 text-base">
                    <input type="checkbox" wire:model="wallet_blocked" class="size-5 accent-primary">Hentikan belanja sementara
                </label>
            </div>
            <x-slot:footer>
                <x-ui.button variant="secondary" x-on:click="open = false">Batal</x-ui.button>
                <x-ui.button wire:click="saveLimits" wire:loading.attr="disabled" wire:target="saveLimits">Simpan</x-ui.button>
            </x-slot:footer>
        </x-ui.modal>

        @if ($card)
            <x-ui.modal name="atur-pin" title="PIN belanja">
                <div class="flex flex-col gap-3">
                    <p class="text-base">PIN diminta saat belanja di atas batas yang diatur pesantren. Berikan hanya kepada {{ $child->name }}.</p>
                    <x-ui.input label="PIN baru (4 sampai 6 angka)" name="pin" type="password" inputmode="numeric" maxlength="6" autocomplete="off" wire:model="pin" />
                    <x-ui.input label="Ulangi PIN" name="pin_confirmation" type="password" inputmode="numeric" maxlength="6" autocomplete="off" wire:model="pin_confirmation" />
                </div>
                <x-slot:footer>
                    <x-ui.button variant="secondary" x-on:click="open = false">Batal</x-ui.button>
                    <x-ui.button wire:click="savePin" wire:loading.attr="disabled" wire:target="savePin">Simpan PIN</x-ui.button>
                </x-slot:footer>
            </x-ui.modal>

            <x-ui.modal name="blokir-kartu" title="Blokir kartu {{ $child->name }}?">
                <div class="flex flex-col gap-3">
                    <p class="text-base">Kartu langsung tidak bisa dipakai. Saldo tetap aman. Untuk memakainya lagi, Anda perlu menghubungi admin pesantren.</p>
                    <x-ui.select label="Alasan" name="blockReason" :options="['Kartu hilang' => 'Kartu hilang', 'Kartu dicuri' => 'Kartu dicuri', 'Dipakai orang lain' => 'Dipakai orang lain', 'Lainnya' => 'Lainnya']" wire:model="blockReason" />
                </div>
                <x-slot:footer>
                    <x-ui.button variant="secondary" x-on:click="open = false">Batal</x-ui.button>
                    <x-ui.button variant="danger" wire:click="blockCard" wire:loading.attr="disabled" wire:target="blockCard">Ya, Blokir Kartu</x-ui.button>
                </x-slot:footer>
            </x-ui.modal>
        @endif

        <x-ui.modal name="struk" title="Struk belanja">
            @if ($order)
                <p class="font-tabular text-sm text-text-muted">{{ $order->receipt_no }}, {{ $order->created_at->setTimezone($tz)->translatedFormat('j M Y, H.i') }}</p>
                <p class="text-base font-semibold">{{ $order->merchant->name }}</p>
                <ul class="mt-2 flex flex-col gap-1 text-base">
                    @foreach ($order->items as $item)
                        <li class="flex justify-between gap-3"><span class="min-w-0 break-words">{{ $item->name }} x{{ $item->qty }}</span><span class="font-tabular shrink-0">{{ Rupiah::format($item->amount) }}</span></li>
                    @endforeach
                </ul>
                <div class="mt-2 flex justify-between gap-3 border-t border-border pt-2 font-semibold"><span>Total</span><span class="font-tabular">{{ Rupiah::format($order->total) }}</span></div>
                <div class="flex justify-between gap-3 text-sm text-text-muted"><span>Sisa saldo saat itu</span><span class="font-tabular">{{ Rupiah::format($order->walletTransaction->balance_after) }}</span></div>
                @if ($order->status === 'void')
                    <x-ui.banner tone="info" class="mt-3">Belanja ini dibatalkan dan saldo sudah dikembalikan.</x-ui.banner>
                @endif
            @endif
        </x-ui.modal>
    @endif
</div>
