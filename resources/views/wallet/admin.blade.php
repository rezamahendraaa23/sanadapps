@php use App\Modules\Finance\Support\Rupiah; @endphp

<div class="flex flex-col gap-4">
    @if ($totalBalance !== null && ! $student)
        <x-ui.hero-card label="Total saldo dompet santri" :value="Rupiah::format($totalBalance)">
            {{ $walletCount }} dompet bersaldo.
        </x-ui.hero-card>
    @endif

    <x-ui.card>
        @if ($student)
            <div class="flex flex-wrap items-center justify-between gap-2">
                <div class="min-w-0">
                    <p class="text-base font-semibold">{{ $student->name }} <span class="font-tabular text-sm font-normal text-text-muted">{{ $student->nis }}</span></p>
                    <p class="text-sm text-text-muted">{{ $student->classroom?->name ?? 'Belum ada kelas' }}</p>
                </div>
                <x-ui.button variant="tertiary" wire:click="clearStudent">Ganti Santri</x-ui.button>
            </div>
        @else
            <x-ui.page-toolbar class="!mb-0">
                <div class="min-w-0 flex-1">
                    <x-ui.input label="Cari nama atau NIS" name="search" type="search" autocomplete="off" wire:model.live.debounce.250ms="search" />
                </div>
                @can('wallet.manage')
                    <x-ui.button variant="secondary" x-data x-on:click="$dispatch('open-modal', 'aturan-dompet')">Aturan Dompet</x-ui.button>
                @endcan
            </x-ui.page-toolbar>
            @foreach ($results as $result)
                <button type="button" wire:key="hasil-{{ $result->uuid }}" wire:click="pick('{{ $result->uuid }}')" class="mt-1 flex min-h-11 w-full items-center justify-between gap-2 rounded-control px-2 text-left hover:bg-primary-soft">
                    <span class="font-semibold">{{ $result->name }}</span><span class="font-tabular text-sm text-text-muted">{{ $result->nis }}</span>
                </button>
            @endforeach
        @endif
    </x-ui.card>

    @if ($student)
        <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_320px]">
            <div class="flex min-w-0 flex-col gap-4">
                <x-ui.hero-card label="Saldo" :value="Rupiah::format($wallet->balance)">
                    @if ($wallet->status === 'blocked') Dompet sedang diblokir. @else Belanja hari ini {{ Rupiah::format($spentToday) }}. @endif
                    @can('wallet.manage')
                        <x-slot:action>
                            <x-ui.button variant="on-primary" x-data x-on:click="$dispatch('open-modal', 'topup-tunai')">Top-up Tunai</x-ui.button>
                            <x-ui.button variant="on-primary" x-data x-on:click="$dispatch('open-modal', 'penyesuaian')">Penyesuaian</x-ui.button>
                            @if ($student->status !== 'active' && $wallet->balance > 0)
                                <x-ui.button variant="on-primary" x-data x-on:click="$dispatch('open-modal', 'tarik-saldo')">Tarik Saldo</x-ui.button>
                            @endif
                        </x-slot:action>
                    @endcan
                </x-ui.hero-card>

                <x-ui.card title="Buku besar">
                    @if ($transactions->isEmpty())
                        <x-ui.empty-state icon="wallet" message="Belum ada transaksi." class="py-2" />
                    @else
                        <x-ui.table caption="Mutasi dompet">
                            <thead><tr><x-ui.th>Waktu</x-ui.th><x-ui.th>Jenis</x-ui.th><x-ui.th>Keterangan</x-ui.th><x-ui.th class="text-right">Nominal</x-ui.th><x-ui.th class="text-right">Saldo</x-ui.th></tr></thead>
                            <tbody>
                                @foreach ($transactions as $transaction)
                                    <tr wire:key="trx-{{ $transaction->uuid }}">
                                        <x-ui.td label="Waktu">{{ $transaction->created_at->setTimezone(config('sanad.display_timezone'))->translatedFormat('j M, H.i') }}</x-ui.td>
                                        <x-ui.td label="Jenis">{{ $transaction->label() }}</x-ui.td>
                                        <x-ui.td label="Keterangan"><span class="break-words">{{ $transaction->merchant?->name ?? $transaction->note ?? '-' }}</span></x-ui.td>
                                        <x-ui.td label="Nominal" class="text-right"><span @class(['font-tabular font-semibold', 'text-success' => $transaction->direction === 'credit'])>{{ $transaction->direction === 'credit' ? '+' : '-' }}{{ Rupiah::format($transaction->amount) }}</span></x-ui.td>
                                        <x-ui.td label="Saldo" class="text-right"><span class="font-tabular">{{ Rupiah::format($transaction->balance_after) }}</span></x-ui.td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </x-ui.table>
                        <div class="mt-4">{{ $transactions->links() }}</div>
                    @endif
                </x-ui.card>
            </div>

            <div class="flex min-w-0 flex-col gap-4">
                <x-ui.card title="Status">
                    <dl class="flex flex-col gap-2 text-base">
                        <div class="flex items-center justify-between gap-2"><dt class="text-text-muted">Dompet</dt><dd><x-ui.status-badge :status="$wallet->status" /></dd></div>
                        <div class="flex items-center justify-between gap-2"><dt class="text-text-muted">Kartu</dt><dd>
                            @if ($card)
                                <x-ui.status-badge :status="$card->status === 'active' ? 'active' : 'blocked'" />
                            @else
                                Belum ada
                            @endif
                        </dd></div>
                    </dl>
                    @can('wallet.manage')
                        <x-ui.button variant="secondary" class="mt-3 w-full" wire:click="toggleWallet" wire:confirm="{{ $wallet->status === 'active' ? 'Blokir dompet ini? Belanja langsung ditolak.' : 'Buka blokir dompet ini?' }}">
                            {{ $wallet->status === 'active' ? 'Blokir Dompet' : 'Buka Blokir Dompet' }}
                        </x-ui.button>
                    @endcan
                </x-ui.card>

                <x-ui.card title="Limit">
                    @can('wallet.manage')
                        <form wire:submit="saveLimits" class="flex flex-col gap-3">
                            <x-ui.input-rupiah label="Limit harian" name="daily_limit" help="Kosongkan bila tanpa limit." wire:model="daily_limit" />
                            <x-ui.input-rupiah label="Limit per transaksi" name="per_transaction_limit" help="Kosongkan bila tanpa limit." wire:model="per_transaction_limit" />
                            <x-ui.button type="submit" variant="secondary" wire:loading.attr="disabled" wire:target="saveLimits">Simpan Limit</x-ui.button>
                        </form>
                    @else
                        <dl class="flex flex-col gap-2 text-base">
                            <div class="flex justify-between gap-2"><dt class="text-text-muted">Harian</dt><dd class="font-tabular">{{ $wallet->daily_limit !== null ? Rupiah::format($wallet->daily_limit) : 'Tanpa limit' }}</dd></div>
                            <div class="flex justify-between gap-2"><dt class="text-text-muted">Per transaksi</dt><dd class="font-tabular">{{ $wallet->per_transaction_limit !== null ? Rupiah::format($wallet->per_transaction_limit) : 'Tanpa limit' }}</dd></div>
                        </dl>
                    @endcan
                </x-ui.card>
            </div>
        </div>

        @can('wallet.manage')
            <x-ui.modal name="topup-tunai" title="Top-up tunai">
                <x-ui.input-rupiah label="Nominal yang diterima" name="topup_amount" required help="Saldo langsung bertambah dan tercatat di kas." wire:model="topup_amount" />
                <x-slot:footer>
                    <x-ui.button variant="secondary" x-on:click="open = false">Batal</x-ui.button>
                    <x-ui.button wire:click="topup" wire:loading.attr="disabled" wire:target="topup">Catat Top-up</x-ui.button>
                </x-slot:footer>
            </x-ui.modal>

            <x-ui.modal name="penyesuaian" title="Penyesuaian saldo">
                <div class="flex flex-col gap-3">
                    <x-ui.select label="Arah" name="adjust_direction" :options="['credit' => 'Tambah saldo', 'debit' => 'Kurangi saldo']" wire:model="adjust_direction" />
                    <x-ui.input-rupiah label="Nominal" name="adjust_amount" required wire:model="adjust_amount" />
                    <x-ui.textarea label="Alasan" name="adjust_reason" required rows="2" help="Tercatat di buku besar dan audit log." wire:model="adjust_reason" />
                </div>
                <x-slot:footer>
                    <x-ui.button variant="secondary" x-on:click="open = false">Batal</x-ui.button>
                    <x-ui.button wire:click="adjust" wire:loading.attr="disabled" wire:target="adjust">Simpan Penyesuaian</x-ui.button>
                </x-slot:footer>
            </x-ui.modal>

            <x-ui.modal name="tarik-saldo" title="Tarik sisa saldo">
                <p class="mb-3 text-base">Seluruh saldo {{ Rupiah::format($wallet->balance) }} diserahkan tunai dan tercatat sebagai kas keluar.</p>
                <x-ui.input label="Diserahkan kepada" name="withdraw_receiver" required wire:model="withdraw_receiver" />
                <x-slot:footer>
                    <x-ui.button variant="secondary" x-on:click="open = false">Batal</x-ui.button>
                    <x-ui.button variant="danger" wire:click="withdraw" wire:loading.attr="disabled" wire:target="withdraw">Serahkan Saldo</x-ui.button>
                </x-slot:footer>
            </x-ui.modal>
        @endcan
    @endif

    @can('wallet.manage')
        <x-ui.modal name="aturan-dompet" title="Aturan dompet">
            <div class="flex flex-col gap-3">
                <x-ui.input-rupiah label="Belanja wajib PIN di atas" name="pin_threshold" required wire:model="pin_threshold" />
                <x-ui.input-rupiah label="Kabari wali bila saldo di bawah" name="low_balance" required wire:model="low_balance" />
                <x-ui.input-rupiah label="Top-up minimum" name="topup_min" required wire:model="topup_min" />
                <x-ui.input-rupiah label="Top-up maksimum" name="topup_max" required wire:model="topup_max" />
            </div>
            <x-slot:footer>
                <x-ui.button variant="secondary" x-on:click="open = false">Batal</x-ui.button>
                <x-ui.button wire:click="saveSettings" wire:loading.attr="disabled" wire:target="saveSettings">Simpan Aturan</x-ui.button>
            </x-slot:footer>
        </x-ui.modal>
    @endcan
</div>
