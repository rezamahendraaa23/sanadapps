@php
    use App\Modules\Finance\Models\Invoice;
    use App\Modules\Finance\Support\Rupiah;

    $children = auth()->user()->guardian?->students()->with('classroom')->orderBy('name')->get() ?? collect();
    $child = $children->firstWhere('uuid', request('anak')) ?? $children->first();
    $financeOn = app(\App\Modules\Core\Support\ModuleRegistry::class)->enabled('finance');
    $open = $financeOn && $child ? Invoice::query()->visibleTo(auth()->user())->where('student_id', $child->id)->open()->orderBy('due_date')->get() : collect();
    $total = $open->sum(fn ($invoice) => $invoice->outstanding());
    $announcements = \App\Modules\Core\Models\Announcement::query()->publishedFor($children)->limit(5)->get();
@endphp

<x-layouts.portal title="Beranda" role="wali">
    @if ($children->isEmpty())
        <x-ui.card>
            <x-ui.empty-state icon="users" message="Nomor Anda belum terhubung ke data santri. Hubungi admin pesantren untuk menghubungkannya." />
        </x-ui.card>
    @else
        @if ($children->count() > 1)
            <nav aria-label="Pilih anak" class="flex gap-2 overflow-x-auto pb-1">
                @foreach ($children as $item)
                    <a href="{{ route('wali.home', ['anak' => $item->uuid]) }}" @if ($item->is($child)) aria-current="true" @endif
                        @class(['inline-flex min-h-11 shrink-0 items-center rounded-full border px-4 text-sm font-semibold', 'border-primary bg-primary-soft text-primary' => $item->is($child), 'border-border bg-surface' => ! $item->is($child)])>{{ $item->name }}</a>
                @endforeach
            </nav>
        @endif

        @if ($financeOn)
            @if ($open->isNotEmpty())
                <x-ui.hero-card label="Total yang harus dibayar" :value="Rupiah::format($total)">
                    {{ $child->name }}. Jatuh tempo terdekat {{ $open->first()->due_date->translatedFormat('j M Y') }}.
                    <x-slot:action>
                        <x-ui.button variant="on-primary" class="w-full" :href="route('wali.checkout', ['tagihan' => $open->pluck('uuid')->join(',')])">Bayar Sekarang</x-ui.button>
                    </x-slot:action>
                </x-ui.hero-card>
            @else
                <x-ui.card>
                    <p class="flex items-center gap-2 text-base"><x-ui.icon name="circle-check" class="text-success" />Semua tagihan {{ $child->name }} sudah lunas.</p>
                </x-ui.card>
            @endif
        @endif

        <x-ui.card>
            <p class="text-base font-semibold">{{ $child->name }}</p>
            <p class="text-sm text-text-muted">NIS {{ $child->nis }}{{ $child->classroom ? ' · '.$child->classroom->name : '' }}</p>
        </x-ui.card>
    @endif

    @include('core.partials.announcements', ['announcements' => $announcements])
</x-layouts.portal>
