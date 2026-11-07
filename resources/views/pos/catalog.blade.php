@php use App\Modules\Finance\Support\Rupiah; @endphp

<div class="flex flex-col gap-4">
    <div class="flex flex-wrap items-center justify-between gap-2">
        <h2 class="font-display text-xl font-semibold break-words">{{ $merchant->name }}</h2>
        @include('pos.partials.merchant-nav')
    </div>

    <x-ui.card>
        <x-ui.page-toolbar>
            <div class="grid w-full min-w-0 gap-3 md:grid-cols-[minmax(0,1fr)_200px]">
                <x-ui.input label="Cari nama atau barcode" name="search" type="search" autocomplete="off" wire:model.live.debounce.250ms="search" />
                <x-ui.select label="Kategori" name="category" placeholder="Semua kategori" :options="$categories->pluck('name', 'id')->all()" wire:model.live="category" />
            </div>
            <div class="flex flex-wrap gap-2">
                <x-ui.button variant="secondary" icon="file-down" :href="route('staff.merchants.pricelist', $merchant)">Daftar Harga</x-ui.button>
                @if ($canManage)
                    @if ($selected !== [])
                        <x-ui.button variant="secondary" x-data x-on:click="$dispatch('open-modal', 'ubah-harga')">Ubah Harga {{ count($selected) }} Produk</x-ui.button>
                    @endif
                    <x-ui.button variant="secondary" x-data x-on:click="$dispatch('open-modal', 'kategori')">Kategori</x-ui.button>
                    <x-ui.button variant="secondary" icon="file-up" :href="route('staff.merchants.import', $merchant)" wire:navigate>Impor</x-ui.button>
                    <x-ui.button icon="plus" wire:click="create">Tambah Produk</x-ui.button>
                @endif
            </div>
        </x-ui.page-toolbar>

        @if ($products->isEmpty())
            <x-ui.empty-state icon="package" :message="$search !== '' || $category !== '' ? 'Tidak ada produk untuk filter ini.' : 'Belum ada produk. Tambahkan satu per satu atau impor dari Excel.'" class="py-2" />
        @else
            <x-ui.table caption="Katalog produk">
                <thead>
                    <tr>
                        @if ($canManage)<x-ui.th><span class="sr-only">Pilih</span></x-ui.th>@endif
                        <x-ui.th>Produk</x-ui.th><x-ui.th>Barcode</x-ui.th><x-ui.th>Kategori</x-ui.th><x-ui.th numeric>Harga</x-ui.th><x-ui.th numeric>Stok</x-ui.th><x-ui.th>Status</x-ui.th>
                        @if ($canManage)<x-ui.th>Aksi</x-ui.th>@endif
                    </tr>
                </thead>
                <tbody>
                    @foreach ($products as $product)
                        <tr wire:key="p-{{ $product->uuid }}">
                            @if ($canManage)
                                <x-ui.td label="Pilih"><label class="inline-flex size-11 items-center justify-center"><input type="checkbox" value="{{ $product->uuid }}" wire:model.live="selected" class="size-5 accent-primary" aria-label="Pilih {{ $product->name }}"></label></x-ui.td>
                            @endif
                            <x-ui.td label="Produk">
                                <span class="flex items-center gap-2">
                                    @if ($product->photo_path)
                                        <img src="{{ route('staff.merchants.product-photo', $product) }}" alt="" class="size-10 shrink-0 rounded-control object-cover">
                                    @endif
                                    <span class="min-w-0 break-words">{{ $product->name }}</span>
                                </span>
                            </x-ui.td>
                            <x-ui.td label="Barcode"><span class="font-tabular">{{ $product->barcode ?: '-' }}</span></x-ui.td>
                            <x-ui.td label="Kategori">
                                {{ $product->category?->name ?? '-' }}
                                @if ($product->category?->is_restricted)<span class="block text-xs text-text-muted">Bisa diblokir wali</span>@endif
                            </x-ui.td>
                            <x-ui.td label="Harga" class="text-right"><span class="font-tabular">{{ Rupiah::format($product->price) }}</span></x-ui.td>
                            <x-ui.td label="Stok" class="text-right">
                                @if ($product->stock === null)
                                    <span class="text-text-muted">Tidak dihitung</span>
                                @else
                                    <span class="font-tabular">{{ $product->stock }}</span>
                                    @if ($product->isLowStock())<span class="block text-xs font-semibold text-warning">Menipis</span>@endif
                                @endif
                            </x-ui.td>
                            <x-ui.td label="Status"><x-ui.status-badge :status="$product->is_active ? 'active' : 'blocked'" /></x-ui.td>
                            @if ($canManage)
                                <x-ui.td label="Aksi"><x-ui.button variant="tertiary" wire:click="edit('{{ $product->uuid }}')">Ubah<span class="sr-only"> {{ $product->name }}</span></x-ui.button></x-ui.td>
                            @endif
                        </tr>
                    @endforeach
                </tbody>
            </x-ui.table>
            <div class="mt-4">{{ $products->links() }}</div>
        @endif
    </x-ui.card>

    @if ($canManage)
        <x-ui.modal name="form-produk" :title="$editing ? 'Ubah produk' : 'Tambah produk'">
            <div class="flex flex-col gap-3">
                <x-ui.input label="Nama produk" name="name" required wire:model="name" />
                <div class="grid gap-3 md:grid-cols-2">
                    <x-ui.input-rupiah label="Harga jual" name="price" required wire:model="price" />
                    <x-ui.input-rupiah label="Harga modal" name="cost_price" help="Untuk menghitung margin." wire:model="cost_price" />
                </div>
                <x-ui.input label="Barcode" name="barcode" autocomplete="off" help="Ketik atau pindai dengan reader." wire:model="barcode" />
                @include('pos.partials.camera-barcode', ['target' => 'barcode'])
                <x-ui.select label="Kategori" name="category_id" placeholder="Tanpa kategori" :options="$categories->pluck('name', 'id')->all()" wire:model="category_id" />
                <label class="flex min-h-11 items-center gap-3 text-base"><input type="checkbox" wire:model.live="track_stock" class="size-5 accent-primary">Hitung stok produk ini</label>
                @if ($track_stock)
                    <div class="grid gap-3 md:grid-cols-2">
                        @unless ($editing && $merchant->products()->whereKey($editing)->whereNotNull('stock')->exists())
                            <x-ui.input label="Stok awal" name="initial_stock" inputmode="numeric" wire:model="initial_stock" />
                        @endunless
                        <x-ui.input label="Stok minimum" name="min_stock" inputmode="numeric" help="Pengelola dikabari saat stok mencapai angka ini." wire:model="min_stock" />
                    </div>
                @endif
                <div class="flex flex-col gap-1">
                    <label for="field-photo" class="text-sm font-semibold">Foto produk</label>
                    <input id="field-photo" type="file" accept="image/jpeg,image/png" wire:model="photo" class="min-h-11 w-full text-base">
                    @error('photo')
                        <p role="alert" class="flex items-start gap-1 text-sm text-danger"><x-ui.icon name="circle-alert" size="sm" class="mt-0.5" />{{ $message }}</p>
                    @enderror
                </div>
                <label class="flex min-h-11 items-center gap-3 text-base"><input type="checkbox" wire:model="is_active" class="size-5 accent-primary">Tampil di kasir</label>
            </div>
            <x-slot:footer>
                <x-ui.button variant="secondary" x-on:click="open = false">Batal</x-ui.button>
                <x-ui.button wire:click="save" wire:loading.attr="disabled" wire:target="save,photo">Simpan</x-ui.button>
            </x-slot:footer>
        </x-ui.modal>

        <x-ui.modal name="ubah-harga" title="Ubah harga {{ count($selected) }} produk">
            <div class="flex flex-col gap-3">
                <x-ui.select label="Cara" name="bulk_mode" :options="['percent' => 'Persen', 'amount' => 'Nominal rupiah']" wire:model="bulk_mode" />
                <x-ui.input label="Perubahan" name="bulk_value" inputmode="numeric" required help="Contoh 10 untuk naik, -10 untuk turun." wire:model="bulk_value" />
            </div>
            <x-slot:footer>
                <x-ui.button variant="secondary" x-on:click="open = false">Batal</x-ui.button>
                <x-ui.button wire:click="applyBulkPrice" wire:loading.attr="disabled" wire:target="applyBulkPrice">Ubah Harga</x-ui.button>
            </x-slot:footer>
        </x-ui.modal>

        <x-ui.modal name="kategori" title="Kategori produk">
            <ul class="mb-3 flex flex-col">
                @forelse ($categories as $item)
                    <li wire:key="kat-{{ $item->uuid }}" class="flex min-h-11 items-center justify-between gap-2 border-b border-border py-1 last:border-b-0 text-base">
                        <span class="min-w-0 break-words">{{ $item->name }}</span>
                        @if ($item->is_restricted)<span class="shrink-0 text-sm text-text-muted">Bisa diblokir wali</span>@endif
                    </li>
                @empty
                    <li class="text-sm text-text-muted">Belum ada kategori.</li>
                @endforelse
            </ul>
            <div class="flex flex-col gap-3">
                <x-ui.input label="Nama kategori baru" name="category_name" wire:model="category_name" />
                <label class="flex min-h-11 items-center gap-3 text-base"><input type="checkbox" wire:model="category_restricted" class="size-5 accent-primary">Wali boleh memblokir kategori ini</label>
            </div>
            <x-slot:footer>
                <x-ui.button variant="secondary" x-on:click="open = false">Tutup</x-ui.button>
                <x-ui.button wire:click="saveCategory" wire:loading.attr="disabled" wire:target="saveCategory">Tambah Kategori</x-ui.button>
            </x-slot:footer>
        </x-ui.modal>
    @endif
</div>
