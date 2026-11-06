@inject('identity', \App\Support\SchoolIdentity::class)

<x-layouts.base :title="$title ?? null">
    <main id="konten" class="pattern-geometric flex min-h-dvh items-center justify-center p-4">
        <div class="flex w-full max-w-[480px] flex-col gap-4">
            <div class="flex flex-col items-center gap-2 text-center">
                @if ($identity->logoUrl())
                    <img src="{{ $identity->logoUrl() }}" alt="Logo {{ $identity->displayName() }}" class="size-16 object-contain">
                @else
                    <x-ui.logo variant="mark" :size="56" />
                @endif
                <div>
                    <p class="font-display text-xl font-semibold">{{ $identity->displayName() }}</p>
                    <p class="text-sm text-text-muted">{{ \App\Support\SchoolIdentity::TAGLINE }}</p>
                </div>
            </div>
            <div class="rounded-card border border-border bg-surface p-4 shadow-sm md:p-6">
                {{ $slot }}
            </div>
        </div>
    </main>
</x-layouts.base>
