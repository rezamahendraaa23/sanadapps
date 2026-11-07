@php
    use App\Modules\Finance\Support\Rupiah;
    $types = ['mart' => 'Mart', 'warung' => 'Warung', 'kantin' => 'Kantin'];
@endphp

<div class="flex flex-col gap-4">
    <x-ui.hero-card label="Penjualan hari ini" :value="Rupiah::format($summary['revenue'])">
        {{ $summary['orders'] }} transaksi di {{ $merchants->count() }} merchant.
        <x-slot:action>
            <x-ui.button variant="on-primary" :href="route('staff.merchants.report.all')" wire:navigate>Laporan Gabungan</x-ui.button>
            @if ($canManageAll)
                <x-ui.button variant="on-primary" icon="plus" wire:click="createMerchant">Tambah Merchant</x-ui.button>
            @endif
        </x-slot:action>
    </x-ui.hero-card>

    @if ($merchants->isEmpty())
        <x-ui.card><x-ui.empty-state icon="store" message="Belum ada merchant." class="py-2" /></x-ui.card>
    @else
        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            @foreach ($merchants as $merchant)
                <x-ui.card wire:key="m-{{ $merchant->uuid }}" class="flex min-w-0 flex-col gap-3">
                    <div class="flex items-start justify-between gap-2">
                        <div class="min-w-0">
                            <h2 class="text-base font-semibold break-words">{{ $merchant->name }}</h2>
                            <p class="text-sm text-text-muted">{{ $types[$merchant->type] }}{{ $merchant->is_active ? '' : ', nonaktif' }}</p>
                        </div>
                        @if ($canManageAll)
                            <x-ui.button variant="tertiary" class="shrink-0" wire:click="editMerchant('{{ $merchant->uuid }}')">Ubah<span class="sr-only"> {{ $merchant->name }}</span></x-ui.button>
                        @endif
                    </div>
                    <dl class="grid grid-cols-3 gap-2">
                        <div><dt class="text-xs text-text-muted">Penjualan</dt><dd class="font-tabular text-sm font-semibold">{{ Rupiah::format($perMerchant[$merchant->id]['revenue']) }}</dd></div>
                        <div><dt class="text-xs text-text-muted">Transaksi</dt><dd class="font-tabular text-sm font-semibold">{{ $perMerchant[$merchant->id]['orders'] }}</dd></div>
                        <div><dt class="text-xs text-text-muted">Stok menipis</dt><dd @class(['font-tabular text-sm font-semibold', 'text-warning' => ($lowStock[$merchant->id] ?? 0) > 0])>{{ $lowStock[$merchant->id] ?? 0 }}</dd></div>
                    </dl>
                    <div class="mt-auto flex flex-wrap gap-2">
                        @can('pos.operate')
                            <x-ui.button :href="route('staff.pos.index', ['merchant' => $merchant->uuid])" wire:navigate>Buka Kasir<span class="sr-only"> {{ $merchant->name }}</span></x-ui.button>
                        @endcan
                        <x-ui.button variant="secondary" :href="route('staff.merchants.catalog', $merchant)" wire:navigate>Katalog<span class="sr-only"> {{ $merchant->name }}</span></x-ui.button>
                        <x-ui.button variant="tertiary" :href="route('staff.merchants.report', $merchant)" wire:navigate>Laporan<span class="sr-only"> {{ $merchant->name }}</span></x-ui.button>
                    </div>
                </x-ui.card>
            @endforeach
        </div>
    @endif

    @if ($canManageAll)
        <x-ui.modal name="form-merchant" :title="$editingMerchant ? 'Ubah merchant' : 'Tambah merchant'">
            <div class="flex flex-col gap-3">
                <x-ui.input label="Nama" name="name" required wire:model="name" />
                <x-ui.select label="Jenis" name="type" :options="$types" wire:model="type" />
                <x-ui.input label="Lokasi" name="location" wire:model="location" />
                <x-ui.input label="Komisi pesantren (%)" name="commission_percent" inputmode="decimal" required wire:model="commission_percent" />
                <x-ui.input-rupiah label="Ambang PIN khusus" name="pin_threshold" help="Kosongkan untuk memakai aturan dompet." wire:model="pin_threshold" />
                <x-ui.select label="Pengelola" name="pic_user_id" placeholder="Belum ada" :options="$staffUsers->pluck('name', 'id')->all()" wire:model="pic_user_id" />
                <fieldset>
                    <legend class="text-sm font-semibold">Kasir</legend>
                    @forelse ($staffUsers as $user)
                        <label wire:key="kasir-{{ $user->id }}" class="flex min-h-11 items-center gap-3 text-base"><input type="checkbox" value="{{ $user->id }}" wire:model="cashiers" class="size-5 accent-primary">{{ $user->name }}</label>
                    @empty
                        <p class="text-sm text-text-muted">Belum ada pengguna ber-role kasir. Tambahkan di Pengguna &amp; Role.</p>
                    @endforelse
                </fieldset>
                <label class="flex min-h-11 items-center gap-3 text-base"><input type="checkbox" wire:model="is_active" class="size-5 accent-primary">Aktif</label>
            </div>
            <x-slot:footer>
                <x-ui.button variant="secondary" x-on:click="open = false">Batal</x-ui.button>
                <x-ui.button wire:click="saveMerchant" wire:loading.attr="disabled" wire:target="saveMerchant">Simpan</x-ui.button>
            </x-slot:footer>
        </x-ui.modal>
    @endif
</div>
