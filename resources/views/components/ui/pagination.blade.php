@if ($paginator->hasPages())
    <nav aria-label="Halaman" class="flex flex-wrap items-center justify-between gap-2">
        <p class="text-sm text-text-muted">Halaman {{ $paginator->currentPage() }} dari {{ $paginator->lastPage() }}</p>
        <div class="flex gap-2">
            <x-ui.button variant="secondary" :disabled="$paginator->onFirstPage()" wire:click="previousPage('{{ $paginator->getPageName() }}')">Sebelumnya</x-ui.button>
            <x-ui.button variant="secondary" :disabled="! $paginator->hasMorePages()" wire:click="nextPage('{{ $paginator->getPageName() }}')">Berikutnya</x-ui.button>
        </div>
    </nav>
@endif
