@php
    $children = auth()->user()->guardian?->students()->with('classroom')->orderBy('name')->get() ?? collect();
    $announcements = \App\Modules\Core\Models\Announcement::query()->publishedFor($children)->limit(5)->get();
@endphp

<x-layouts.portal title="Beranda" role="wali">
    <section class="flex flex-col gap-3">
        <h2 class="text-lg font-semibold">Anak Anda</h2>
        @forelse ($children as $child)
            <x-ui.card>
                <p class="text-base font-semibold">{{ $child->name }}</p>
                <p class="text-sm text-text-muted">NIS {{ $child->nis }}{{ $child->classroom ? ' · '.$child->classroom->name : '' }}</p>
            </x-ui.card>
        @empty
            <x-ui.card>
                <x-ui.empty-state icon="users" message="Nomor Anda belum terhubung ke data santri. Hubungi admin pesantren untuk menghubungkannya." />
            </x-ui.card>
        @endforelse
    </section>

    @include('core.partials.announcements', ['announcements' => $announcements])
</x-layouts.portal>
