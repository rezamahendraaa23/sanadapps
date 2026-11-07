@php use App\Modules\Finance\Support\Rupiah; @endphp

<div class="flex flex-col gap-4">
    <div class="flex flex-wrap items-center justify-between gap-2">
        <h2 class="font-display text-xl font-semibold break-words">{{ $merchant->name }}</h2>
        @include('pos.partials.merchant-nav')
    </div>

    <div class="grid gap-3 sm:grid-cols-3">
        <div class="rounded-card bg-primary p-4 text-on-primary shadow-md">
            <p class="text-sm opacity-80">Utang ke pemasok</p>
            <p class="font-display font-tabular text-2xl font-semibold">{{ Rupiah::format($debt) }}</p>
        </div>
        <button type="button" wire:click="selectTab('belanja')" class="rounded-card border border-border bg-surface p-4 text-left shadow-sm hover:border-primary">
            <p class="text-sm text-text-muted">Perlu belanja</p>
            <p @class(['font-display font-tabular text-2xl font-semibold', 'text-warning' => $lowStock->isNotEmpty()])>{{ $lowStock->count() }} produk</p>
        </button>
        <button type="button" wire:click="selectTab('kedaluwarsa')" class="rounded-card border border-border bg-surface p-4 text-left shadow-sm hover:border-primary">
            <p class="text-sm text-text-muted">Cek kedaluwarsa</p>
            <p class="font-display text-2xl font-semibold">{{ \App\Modules\Pos\Livewire\Purchasing::EXPIRY_DAYS }} hari ke depan</p>
        </button>
    </div>

    <div class="flex flex-wrap items-center justify-between gap-2">
        <div role="tablist" aria-label="Bagian pembelian" class="flex flex-wrap gap-1">
            @foreach (['pembelian' => 'Pembelian', 'pemasok' => 'Pemasok', 'belanja' => 'Perlu Belanja', 'kedaluwarsa' => 'Kedaluwarsa'] as $key => $label)
                <x-ui.tab :selected="$tab === $key" wire:key="tab-{{ $key }}" wire:click="selectTab('{{ $key }}')">{{ $label }}</x-ui.tab>
            @endforeach
        </div>
        @if ($canManage)
            <x-ui.button icon="plus" wire:click="newPurchase">Catat Pembelian</x-ui.button>
        @endif
    </div>

    @if ($tab === 'pembelian')
        <x-ui.card title="Riwayat pembelian">
            @if ($purchases->isEmpty())
                <x-ui.empty-state icon="truck" message="Belum ada pembelian. Catat barang yang datang dari pemasok agar stok dan harga modal ikut benar." class="py-2" />
            @else
                <x-ui.table caption="Riwayat pembelian">
                    <thead><tr><x-ui.th>Nomor</x-ui.th><x-ui.th>Pemasok</x-ui.th><x-ui.th numeric>Total</x-ui.th><x-ui.th numeric>Sisa utang</x-ui.th><x-ui.th>Status</x-ui.th>@if ($canManage)<x-ui.th>Aksi</x-ui.th>@endif</tr></thead>
                    <tbody>
                        @foreach ($purchases as $purchase)
                            <tr wire:key="p-{{ $purchase->uuid }}">
                                <x-ui.td label="Nomor"><span class="font-tabular font-semibold">{{ $purchase->purchase_no }}</span><span class="block text-xs text-text-muted">{{ $purchase->purchased_on->translatedFormat('j M Y') }}</span></x-ui.td>
                                <x-ui.td label="Pemasok">{{ $purchase->supplier?->name ?? 'Tanpa pemasok' }}</x-ui.td>
                                <x-ui.td label="Total" class="text-right"><span class="font-tabular">{{ Rupiah::format($purchase->total) }}</span></x-ui.td>
                                <x-ui.td label="Sisa utang" class="text-right"><span class="font-tabular font-semibold">{{ Rupiah::format($purchase->outstanding()) }}</span>@if ($purchase->due_on && $purchase->outstanding() > 0)<span class="block text-xs text-text-muted">Jatuh tempo {{ $purchase->due_on->translatedFormat('j M') }}</span>@endif</x-ui.td>
                                <x-ui.td label="Status">
                                    <span @class(['inline-flex rounded-full px-2 py-0.5 text-xs font-semibold', 'bg-success-soft' => $purchase->outstanding() === 0, 'bg-warning-soft' => $purchase->outstanding() > 0])>{{ $purchase->statusLabel() }}</span>
                                </x-ui.td>
                                @if ($canManage)
                                    <x-ui.td label="Aksi">
                                        @if ($purchase->outstanding() > 0)
                                            <x-ui.button variant="tertiary" wire:click="askPay('{{ $purchase->uuid }}')">Bayar<span class="sr-only"> {{ $purchase->purchase_no }}</span></x-ui.button>
                                        @endif
                                    </x-ui.td>
                                @endif
                            </tr>
                        @endforeach
                    </tbody>
                </x-ui.table>
                <div class="mt-3">{{ $purchases->links() }}</div>
            @endif
        </x-ui.card>
    @elseif ($tab === 'pemasok')
        <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_320px]">
            <x-ui.card title="Pemasok">
                @forelse ($suppliers as $supplier)
                    <div wire:key="sup-{{ $supplier->uuid }}" class="flex items-center justify-between gap-3 border-b border-border py-3 last:border-b-0">
                        <span class="min-w-0">
                            <span class="block font-semibold break-words">{{ $supplier->name }}</span>
                            <span class="block text-sm text-text-muted">{{ $supplier->phone ?: 'Tanpa nomor' }}</span>
                        </span>
                        <span class="shrink-0 text-right"><span class="block text-xs text-text-muted">Utang</span><span class="font-tabular font-semibold">{{ Rupiah::format((int) $supplier->debt) }}</span></span>
                    </div>
                @empty
                    <x-ui.empty-state icon="truck" message="Belum ada pemasok." class="py-2" />
                @endforelse
            </x-ui.card>
            @if ($canManage)
                <x-ui.card title="Tambah pemasok">
                    <form wire:submit="saveSupplier" class="flex flex-col gap-3">
                        <x-ui.input label="Nama pemasok" name="supplier_name" required wire:model="supplier_name" />
                        <x-ui.input label="Nomor telepon" name="supplier_phone" inputmode="tel" wire:model="supplier_phone" />
                        <x-ui.button type="submit">Simpan Pemasok</x-ui.button>
                    </form>
                </x-ui.card>
            @endif
        </div>
    @elseif ($tab === 'belanja')
        <x-ui.card title="Perlu belanja">
            <x-slot:actions>
                @if ($canManage && $lowStock->isNotEmpty())
                    <x-ui.button variant="secondary" icon="truck" wire:click="restockAll">Buat Pembelian dari Daftar Ini</x-ui.button>
                @endif
            </x-slot:actions>
            @if ($lowStock->isEmpty())
                <x-ui.empty-state icon="package-check" message="Semua stok di atas batas minimum." class="py-2" />
            @else
                <ul class="flex flex-col">
                    @foreach ($lowStock as $product)
                        <li wire:key="low-{{ $product->uuid }}" class="flex items-center justify-between gap-3 border-b border-border py-3 last:border-b-0">
                            <span class="min-w-0 break-words font-semibold">{{ $product->name }}</span>
                            <span class="font-tabular shrink-0 text-sm"><span @class(['font-semibold', 'text-danger' => $product->stock === 0, 'text-warning' => $product->stock > 0])>{{ $product->stock }}</span> dari minimum {{ $product->min_stock }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-ui.card>
    @else
        <x-ui.card title="Segera kedaluwarsa">
            <p class="mb-3 text-sm text-text-muted">Dari tanggal kedaluwarsa yang diisi saat pembelian. Sisa adalah perkiraan paling banyak, karena stok dihitung per produk, bukan per kiriman.</p>
            @if ($expiring->isEmpty())
                <x-ui.empty-state icon="calendar-check" message="Tidak ada barang yang kedaluwarsa dalam {{ \App\Modules\Pos\Livewire\Purchasing::EXPIRY_DAYS }} hari." class="py-2" />
            @else
                <ul class="flex flex-col">
                    @foreach ($expiring as $row)
                        <li wire:key="exp-{{ $row['item']->id }}" class="flex items-center justify-between gap-3 border-b border-border py-3 last:border-b-0">
                            <span class="min-w-0">
                                <span class="block font-semibold break-words">{{ $row['item']->product->name }}</span>
                                <span class="font-tabular block text-sm text-text-muted">Sisa paling banyak {{ $row['left'] }}</span>
                            </span>
                            <span @class(['inline-flex shrink-0 items-center gap-1 rounded-full px-3 py-1 text-sm font-semibold', 'bg-danger-soft' => $row['expired'], 'bg-warning-soft' => ! $row['expired']])>
                                <x-ui.icon name="calendar-x" size="sm" />{{ $row['expired'] ? 'Lewat' : 'Sampai' }} {{ $row['item']->expires_on->translatedFormat('j M Y') }}
                            </span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-ui.card>
    @endif

    @if ($canManage)
        <x-ui.modal name="form-pembelian" title="Catat pembelian">
            <div class="flex flex-col gap-3">
                <div class="grid gap-3 sm:grid-cols-2">
                    <x-ui.select label="Pemasok" name="supplier_id" placeholder="Tanpa pemasok" :options="$suppliers->pluck('name', 'uuid')->all()" wire:model="supplier_id" />
                    <x-ui.input label="Tanggal beli" name="purchased_on" type="date" required wire:model="purchased_on" />
                </div>
                @error('rows')<p role="alert" class="flex items-center gap-1 text-sm font-semibold text-danger"><x-ui.icon name="circle-alert" size="sm" />{{ $message }}</p>@enderror
                @foreach ($rows as $index => $row)
                    <fieldset wire:key="row-{{ $index }}" class="grid gap-2 rounded-control border border-border p-3 sm:grid-cols-2">
                        <legend class="px-1 text-sm font-semibold">Barang {{ $index + 1 }}</legend>
                        <div class="sm:col-span-2"><x-ui.select label="Produk" name="rows.{{ $index }}.product" placeholder="Pilih produk" :options="$products" wire:model="rows.{{ $index }}.product" /></div>
                        <x-ui.input label="Jumlah" name="rows.{{ $index }}.qty" inputmode="numeric" required wire:model="rows.{{ $index }}.qty" />
                        <x-ui.input-rupiah label="Harga beli satuan" name="rows.{{ $index }}.unit_cost" required wire:model="rows.{{ $index }}.unit_cost" />
                        <x-ui.input label="Kedaluwarsa" name="rows.{{ $index }}.expires_on" type="date" help="Kosongkan bila tidak ada." wire:model="rows.{{ $index }}.expires_on" />
                        @if (count($rows) > 1)
                            <div class="flex items-end"><x-ui.button variant="tertiary" wire:click="removeRow({{ $index }})">Hapus Baris<span class="sr-only"> {{ $index + 1 }}</span></x-ui.button></div>
                        @endif
                    </fieldset>
                @endforeach
                <x-ui.button variant="secondary" icon="plus" wire:click="addRow">Tambah Barang</x-ui.button>
                <div class="grid gap-3 sm:grid-cols-2">
                    <x-ui.input-rupiah label="Dibayar sekarang" name="paid_now" help="Kosongkan bila belum dibayar." wire:model="paid_now" />
                    <x-ui.input label="Jatuh tempo" name="due_on" type="date" wire:model="due_on" />
                </div>
                <x-ui.input label="Catatan" name="note" wire:model="note" />
            </div>
            <x-slot:footer>
                <x-ui.button variant="secondary" x-on:click="open = false">Batal</x-ui.button>
                <x-ui.button wire:click="savePurchase" wire:loading.attr="disabled" wire:target="savePurchase">Simpan Pembelian</x-ui.button>
            </x-slot:footer>
        </x-ui.modal>

        <x-ui.modal name="bayar-pemasok" title="Bayar pemasok">
            <div class="flex flex-col gap-3">
                <x-ui.input-rupiah label="Nominal" name="pay_amount" required wire:model="pay_amount" />
                <x-ui.select label="Cara bayar" name="pay_method" :options="['cash' => 'Tunai', 'transfer' => 'Transfer']" wire:model="pay_method" />
            </div>
            <x-slot:footer>
                <x-ui.button variant="secondary" x-on:click="open = false">Batal</x-ui.button>
                <x-ui.button wire:click="pay" wire:loading.attr="disabled" wire:target="pay">Catat Pembayaran</x-ui.button>
            </x-slot:footer>
        </x-ui.modal>
    @endif
</div>
