@php
    use App\Modules\Finance\Support\Rupiah;
    $types = ['mart' => 'Mart', 'warung' => 'Warung', 'kantin' => 'Kantin'];
@endphp

<div class="grid gap-4 lg:grid-cols-[320px_minmax(0,1fr)]">
    <x-ui.card title="Merchant" class="min-w-0">
        <x-slot:actions>
            <x-ui.button variant="secondary" icon="plus" wire:click="createMerchant">Tambah</x-ui.button>
        </x-slot:actions>
        @forelse ($merchants as $merchant)
            <button type="button" wire:key="m-{{ $merchant->uuid }}" wire:click="$set('selected', '{{ $merchant->uuid }}')" @if ($selectedMerchant?->is($merchant)) aria-current="true" @endif
                @class(['flex min-h-11 w-full items-center justify-between gap-2 rounded-control px-2 py-2 text-left', 'bg-primary-soft' => $selectedMerchant?->is($merchant), 'hover:bg-primary-soft' => ! $selectedMerchant?->is($merchant)])>
                <span class="min-w-0"><span class="block font-semibold break-words">{{ $merchant->name }}</span><span class="block text-sm text-text-muted">{{ $types[$merchant->type] }}, {{ $merchant->products_count }} produk{{ $merchant->is_active ? '' : ', nonaktif' }}</span></span>
                <x-ui.icon name="chevron-right" class="shrink-0" />
            </button>
        @empty
            <x-ui.empty-state icon="store" message="Belum ada merchant." class="py-2" />
        @endforelse
    </x-ui.card>

    <div class="flex min-w-0 flex-col gap-4">
        @if ($selectedMerchant)
            <x-ui.card :title="$selectedMerchant->name">
                <x-slot:actions>
                    <x-ui.button variant="secondary" wire:click="editMerchant('{{ $selectedMerchant->uuid }}')">Ubah<span class="sr-only"> merchant</span></x-ui.button>
                </x-slot:actions>
                <dl class="grid gap-3 md:grid-cols-3">
                    <div><dt class="text-sm text-text-muted">Saldo merchant</dt><dd class="font-display font-tabular text-2xl font-semibold">{{ Rupiah::format($balance) }}</dd></div>
                    <div><dt class="text-sm text-text-muted">Komisi pesantren</dt><dd class="font-tabular text-base">{{ (float) $selectedMerchant->commission_percent }}%</dd></div>
                    <div><dt class="text-sm text-text-muted">Lokasi</dt><dd class="text-base break-words">{{ $selectedMerchant->location ?: 'Belum diisi' }}</dd></div>
                </dl>
            </x-ui.card>

            <x-ui.card title="Produk">
                <x-slot:actions>
                    <x-ui.button icon="plus" wire:click="createProduct">Tambah Produk</x-ui.button>
                </x-slot:actions>
                @if ($products->isEmpty())
                    <x-ui.empty-state icon="package" message="Belum ada produk." class="py-2" />
                @else
                    <x-ui.table caption="Produk merchant">
                        <thead><tr><x-ui.th>Nama</x-ui.th><x-ui.th>Barcode</x-ui.th><x-ui.th class="text-right">Harga</x-ui.th><x-ui.th>Status</x-ui.th><x-ui.th>Aksi</x-ui.th></tr></thead>
                        <tbody>
                            @foreach ($products as $product)
                                <tr wire:key="p-{{ $product->uuid }}">
                                    <x-ui.td label="Nama"><span class="break-words">{{ $product->name }}</span></x-ui.td>
                                    <x-ui.td label="Barcode"><span class="font-tabular">{{ $product->barcode ?: '-' }}</span></x-ui.td>
                                    <x-ui.td label="Harga" class="text-right"><span class="font-tabular">{{ Rupiah::format($product->price) }}</span></x-ui.td>
                                    <x-ui.td label="Status"><x-ui.status-badge :status="$product->is_active ? 'active' : 'blocked'" /></x-ui.td>
                                    <x-ui.td label="Aksi"><x-ui.button variant="tertiary" wire:click="editProduct('{{ $product->uuid }}')">Ubah<span class="sr-only"> {{ $product->name }}</span></x-ui.button></x-ui.td>
                                </tr>
                            @endforeach
                        </tbody>
                    </x-ui.table>
                    <div class="mt-4">{{ $products->links() }}</div>
                @endif
            </x-ui.card>
        @else
            <x-ui.card>
                <x-ui.empty-state icon="store" message="Pilih merchant untuk melihat produk dan saldonya." class="py-2" />
            </x-ui.card>
        @endif
    </div>

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

    <x-ui.modal name="form-produk" :title="$editingProduct ? 'Ubah produk' : 'Tambah produk'">
        <div class="flex flex-col gap-3">
            <x-ui.input label="Nama produk" name="product_name" required wire:model="product_name" />
            <x-ui.input-rupiah label="Harga" name="product_price" required wire:model="product_price" />
            <x-ui.input label="Barcode" name="product_barcode" autocomplete="off" wire:model="product_barcode" />
            <label class="flex min-h-11 items-center gap-3 text-base"><input type="checkbox" wire:model="product_active" class="size-5 accent-primary">Tampil di kasir</label>
        </div>
        <x-slot:footer>
            <x-ui.button variant="secondary" x-on:click="open = false">Batal</x-ui.button>
            <x-ui.button wire:click="saveProduct" wire:loading.attr="disabled" wire:target="saveProduct">Simpan</x-ui.button>
        </x-slot:footer>
    </x-ui.modal>
</div>
