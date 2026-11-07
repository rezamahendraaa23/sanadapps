@inject('identity', \App\Support\SchoolIdentity::class)
@php
    $ppdbOn = app(\App\Modules\Core\Support\ModuleRegistry::class)->enabled('ppdb');
    $ppdbOpen = $ppdbOn && \App\Modules\Admission\Models\AdmissionPeriod::accepting() !== null;
@endphp

<x-layouts.base title="Beranda">
    <main id="konten" class="pattern-geometric flex min-h-dvh items-center justify-center p-6">
        <div class="flex w-full max-w-[480px] flex-col items-center gap-4 rounded-card border border-border bg-surface p-8 text-center shadow-sm">
            @if ($identity->logoUrl())
                <img src="{{ $identity->logoUrl() }}" alt="Logo {{ $identity->displayName() }}" class="h-16 w-16 object-contain">
            @else
                <x-ui.logo variant="mark" :size="64" />
            @endif
            <div>
                <h1 class="font-display text-2xl font-semibold">{{ $identity->displayName() }}</h1>
                <p class="mt-1 text-base text-text-muted">{{ \App\Support\SchoolIdentity::TAGLINE }}</p>
            </div>
            <div class="flex w-full flex-col gap-2">
                @if ($ppdbOpen)
                    <x-ui.button class="w-full" :href="route('ppdb.register')">Daftar Santri Baru</x-ui.button>
                @endif
                <x-ui.button :variant="$ppdbOpen ? 'secondary' : 'primary'" class="w-full" :href="route('login')">Masuk</x-ui.button>
                @if ($ppdbOn)
                    <x-ui.button variant="tertiary" class="w-full" :href="route('ppdb.status')">Cek Status Pendaftaran</x-ui.button>
                @endif
            </div>
        </div>
    </main>
</x-layouts.base>
