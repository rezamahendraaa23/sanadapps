<div class="flex flex-col gap-4">
    <div class="flex flex-wrap items-center justify-between gap-2">
        <h2 class="font-display text-xl font-semibold break-words">{{ $merchant->name }}</h2>
        @include('pos.partials.merchant-nav')
    </div>

    <div class="flex flex-wrap items-center justify-between gap-2">
        <p class="text-base text-text-muted">Promo dipakai otomatis di kasir. Bila beberapa promo cocok, yang potongannya paling besar yang dipakai.</p>
        @if ($canManage)
            <x-ui.button icon="plus" wire:click="create">Buat Promo</x-ui.button>
        @endif
    </div>

    @if ($promotions->isEmpty())
        <x-ui.card><x-ui.empty-state icon="tag" message="Belum ada promo." class="py-2" /></x-ui.card>
    @else
        <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
            @foreach ($promotions as $promo)
                @php($isRunning = in_array($promo->id, $running, true))
                <article wire:key="promo-{{ $promo->uuid }}" @class(['relative flex flex-col gap-2 overflow-hidden rounded-card border p-4 shadow-sm', 'border-primary bg-surface' => $isRunning, 'border-border bg-bg' => ! $isRunning])>
                    <span @class(['absolute top-3 right-3 rounded-full px-2 py-0.5 text-xs font-semibold', 'bg-success-soft' => $isRunning, 'bg-surface text-text-muted' => ! $isRunning])>{{ $isRunning ? 'Berjalan' : ($promo->is_active ? 'Terjadwal atau selesai' : 'Dimatikan') }}</span>
                    <span class="font-display inline-flex size-12 -rotate-6 items-center justify-center rounded-card bg-accent-soft text-lg font-semibold text-accent">{{ $promo->kind === 'percent' ? $promo->value.'%' : 'Rp' }}</span>
                    <h3 class="pr-24 text-base font-semibold break-words">{{ $promo->name }}</h3>
                    <p class="text-sm">{{ $promo->label() }}</p>
                    <p class="text-sm text-text-muted">Untuk {{ $promo->product?->name ?? ($promo->category ? 'kategori '.$promo->category->name : 'semua produk') }}</p>
                    <p class="text-sm text-text-muted">{{ $promo->starts_on?->translatedFormat('j M Y') ?? 'Sejak dibuat' }} sampai {{ $promo->ends_on?->translatedFormat('j M Y') ?? 'seterusnya' }}</p>
                    @if ($canManage)
                        <div class="mt-auto flex flex-wrap gap-2 pt-2">
                            <x-ui.button variant="secondary" wire:click="edit('{{ $promo->uuid }}')">Ubah<span class="sr-only"> {{ $promo->name }}</span></x-ui.button>
                            <x-ui.button variant="tertiary" wire:click="toggle('{{ $promo->uuid }}')">{{ $promo->is_active ? 'Matikan' : 'Nyalakan' }}<span class="sr-only"> {{ $promo->name }}</span></x-ui.button>
                        </div>
                    @endif
                </article>
            @endforeach
        </div>
    @endif

    @if ($canManage)
        <x-ui.modal name="form-promo" :title="$editing ? 'Ubah promo' : 'Buat promo'">
            <div class="flex flex-col gap-3">
                <x-ui.input label="Nama promo" name="name" required help="Contoh: Jumat Berkah, Beli 3 Roti." wire:model="name" />
                <x-ui.select label="Berlaku untuk" name="target" :options="['all' => 'Semua produk', 'product' => 'Satu produk', 'category' => 'Satu kategori']" wire:model.live="target" />
                @if ($target === 'product')
                    <x-ui.select label="Produk" name="product" placeholder="Pilih produk" :options="$products" wire:model="product" />
                @elseif ($target === 'category')
                    <x-ui.select label="Kategori" name="category" placeholder="Pilih kategori" :options="$categories" wire:model="category" />
                @endif
                <div class="grid gap-3 md:grid-cols-2">
                    <x-ui.select label="Jenis potongan" name="kind" :options="['percent' => 'Persen', 'amount' => 'Rupiah per barang']" wire:model.live="kind" />
                    @if ($kind === 'percent')
                        <x-ui.input label="Besar potongan (%)" name="value" inputmode="numeric" required wire:model="value" />
                    @else
                        <x-ui.input-rupiah label="Potongan per barang" name="value" required wire:model="value" />
                    @endif
                </div>
                <x-ui.input label="Jumlah minimal" name="min_qty" inputmode="numeric" required help="Isi 3 untuk promo beli 3 lebih murah." wire:model="min_qty" />
                <div class="grid gap-3 md:grid-cols-2">
                    <x-ui.input label="Mulai" name="starts_on" type="date" wire:model="starts_on" />
                    <x-ui.input label="Selesai" name="ends_on" type="date" help="Kosongkan bila tanpa batas." wire:model="ends_on" />
                </div>
            </div>
            <x-slot:footer>
                <x-ui.button variant="secondary" x-on:click="open = false">Batal</x-ui.button>
                <x-ui.button wire:click="save" wire:loading.attr="disabled" wire:target="save">Simpan Promo</x-ui.button>
            </x-slot:footer>
        </x-ui.modal>
    @endif
</div>
