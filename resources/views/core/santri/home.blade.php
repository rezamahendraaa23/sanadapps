@php
    $student = auth()->user()->student?->load('classroom', 'dormitory');
    $announcements = \App\Modules\Core\Models\Announcement::query()->publishedFor(array_filter([$student]))->limit(5)->get();
@endphp

<x-layouts.portal title="Beranda" role="santri">
    <x-ui.card>
        <p class="text-lg font-semibold">Assalamu'alaikum, {{ $student?->name ?? auth()->user()->name }}</p>
        @if ($student)
            <p class="text-sm text-text-muted">NIS {{ $student->nis }}{{ $student->classroom ? ' · '.$student->classroom->name : '' }}{{ $student->dormitory ? ' · '.$student->dormitory->name : '' }}</p>
        @else
            <p class="text-sm text-text-muted">Akun ini belum terhubung ke data santri. Hubungi admin pesantren.</p>
        @endif
    </x-ui.card>

    @if ($student && app(\App\Modules\Core\Support\ModuleRegistry::class)->enabled('wallet'))
        <x-ui.card>
            <p class="text-sm text-text-muted">Saldo</p>
            <p class="font-display font-tabular text-2xl font-semibold">{{ \App\Modules\Finance\Support\Rupiah::format(\App\Modules\Wallet\Models\Wallet::forStudent($student)->balance) }}</p>
        </x-ui.card>
    @endif

    @include('core.partials.announcements', ['announcements' => $announcements])
</x-layouts.portal>
