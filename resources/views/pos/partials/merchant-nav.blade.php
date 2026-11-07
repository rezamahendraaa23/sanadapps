<nav aria-label="Bagian merchant" class="flex flex-wrap items-center gap-1">
    <a href="{{ route('staff.merchants.index') }}" wire:navigate class="inline-flex min-h-11 items-center gap-1 rounded-control px-3 text-base font-semibold text-text-muted hover:bg-primary-soft"><x-ui.icon name="chevron-left" size="sm" />Semua</a>
    @foreach (['staff.merchants.catalog' => 'Katalog', 'staff.merchants.stock' => 'Stok', 'staff.merchants.purchases' => 'Pembelian', 'staff.merchants.promotions' => 'Promo', 'staff.merchants.orders' => 'Transaksi', 'staff.merchants.report' => 'Laporan'] as $route => $label)
        <a href="{{ route($route, $merchant) }}" wire:navigate @if (request()->routeIs($route)) aria-current="page" @endif
            @class(['inline-flex min-h-11 items-center rounded-control px-4 text-base font-semibold', 'bg-primary text-on-primary' => request()->routeIs($route), 'text-text-muted hover:bg-primary-soft' => ! request()->routeIs($route)])>{{ $label }}</a>
    @endforeach
</nav>
