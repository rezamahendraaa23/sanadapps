<div class="flex flex-col gap-4">
    <div class="flex flex-wrap items-center justify-between gap-2">
        <h2 class="font-display text-xl font-semibold break-words">{{ $merchant->name }}</h2>
        @include('pos.partials.merchant-nav')
    </div>

    @if ($opnameResult)
        <x-ui.banner :tone="$opnameResult['difference'] === 0 ? 'success' : 'warning'">
            Opname {{ $opnameResult['name'] }}: sistem {{ $opnameResult['system'] }}, hitung fisik {{ $opnameResult['counted'] }}.
            {{ $opnameResult['difference'] === 0 ? 'Cocok.' : 'Selisih '.($opnameResult['difference'] > 0 ? '+' : '').$opnameResult['difference'].' dicatat sebagai penyesuaian.' }}
        </x-ui.banner>
    @endif

    <x-ui.card title="Stok">
        <x-slot:actions>
            <label class="flex min-h-11 items-center gap-2 text-base"><input type="checkbox" wire:model.live="lowOnly" class="size-5 accent-primary">Hanya yang menipis</label>
        </x-slot:actions>
        @if ($products->isEmpty())
            <x-ui.empty-state icon="package" :message="$lowOnly ? 'Tidak ada stok yang menipis.' : 'Belum ada produk yang dihitung stoknya. Aktifkan di form produk.'" class="py-2" />
        @else
            <x-ui.table caption="Stok produk">
                <thead><tr><x-ui.th>Produk</x-ui.th><x-ui.th numeric>Stok</x-ui.th><x-ui.th numeric>Minimum</x-ui.th>@if ($canManage)<x-ui.th>Aksi</x-ui.th>@endif</tr></thead>
                <tbody>
                    @foreach ($products as $product)
                        <tr wire:key="s-{{ $product->uuid }}">
                            <x-ui.td label="Produk"><span class="break-words">{{ $product->name }}</span></x-ui.td>
                            <x-ui.td label="Stok" class="text-right">
                                <span class="font-tabular font-semibold">{{ $product->stock }}</span>
                                @if ($product->isLowStock())<span class="inline-flex items-center gap-1 text-xs font-semibold text-warning"><x-ui.icon name="triangle-alert" size="sm" />Menipis</span>@endif
                            </x-ui.td>
                            <x-ui.td label="Minimum" class="text-right"><span class="font-tabular">{{ $product->min_stock ?? '-' }}</span></x-ui.td>
                            @if ($canManage)
                                <x-ui.td label="Aksi">
                                    <div class="flex flex-wrap gap-1">
                                        <x-ui.button variant="tertiary" wire:click="open('{{ $product->uuid }}', 'in')">Masuk<span class="sr-only"> {{ $product->name }}</span></x-ui.button>
                                        <x-ui.button variant="tertiary" wire:click="open('{{ $product->uuid }}', 'out')">Keluar<span class="sr-only"> {{ $product->name }}</span></x-ui.button>
                                        <x-ui.button variant="tertiary" wire:click="open('{{ $product->uuid }}', 'opname')">Opname<span class="sr-only"> {{ $product->name }}</span></x-ui.button>
                                    </div>
                                </x-ui.td>
                            @endif
                        </tr>
                    @endforeach
                </tbody>
            </x-ui.table>
            <div class="mt-4">{{ $products->links() }}</div>
        @endif
    </x-ui.card>

    <x-ui.card title="Riwayat mutasi">
        @if ($movements->isEmpty())
            <x-ui.empty-state icon="history" message="Belum ada mutasi." class="py-2" />
        @else
            <x-ui.table caption="Riwayat mutasi stok">
                <thead><tr><x-ui.th>Waktu</x-ui.th><x-ui.th>Produk</x-ui.th><x-ui.th>Jenis</x-ui.th><x-ui.th numeric>Jumlah</x-ui.th><x-ui.th numeric>Sisa</x-ui.th><x-ui.th>Catatan</x-ui.th></tr></thead>
                <tbody>
                    @foreach ($movements as $movement)
                        <tr wire:key="m-{{ $movement->uuid }}">
                            <x-ui.td label="Waktu">{{ $movement->created_at->setTimezone(config('sanad.display_timezone'))->translatedFormat('j M, H.i') }}</x-ui.td>
                            <x-ui.td label="Produk"><span class="break-words">{{ $movement->product->name }}</span></x-ui.td>
                            <x-ui.td label="Jenis">{{ $labels[$movement->type] }}</x-ui.td>
                            <x-ui.td label="Jumlah" class="text-right"><span class="font-tabular">{{ $movement->quantity > 0 ? '+' : '' }}{{ $movement->quantity }}</span></x-ui.td>
                            <x-ui.td label="Sisa" class="text-right"><span class="font-tabular">{{ $movement->stock_after }}</span></x-ui.td>
                            <x-ui.td label="Catatan"><span class="break-words">{{ $movement->note ?: '-' }}</span></x-ui.td>
                        </tr>
                    @endforeach
                </tbody>
            </x-ui.table>
            <div class="mt-4">{{ $movements->links() }}</div>
        @endif
    </x-ui.card>

    @if ($canManage)
        <x-ui.modal name="gerak-stok" :title="['in' => 'Stok masuk', 'out' => 'Stok keluar', 'opname' => 'Opname'][$action].($targetProduct ? ': '.$targetProduct->name : '')">
            <div class="flex flex-col gap-3">
                @if ($targetProduct)<p class="text-base">Stok di sistem: <strong class="font-tabular">{{ $targetProduct->stock }}</strong></p>@endif
                <x-ui.input :label="$action === 'opname' ? 'Hasil hitung fisik' : 'Jumlah'" name="quantity" inputmode="numeric" required wire:model="quantity" />
                <x-ui.input :label="$action === 'out' ? 'Alasan' : 'Catatan'" name="note" :required="$action === 'out'" wire:model="note" />
            </div>
            <x-slot:footer>
                <x-ui.button variant="secondary" x-on:click="open = false">Batal</x-ui.button>
                <x-ui.button wire:click="submit" wire:loading.attr="disabled" wire:target="submit">Simpan</x-ui.button>
            </x-slot:footer>
        </x-ui.modal>
    @endif
</div>
