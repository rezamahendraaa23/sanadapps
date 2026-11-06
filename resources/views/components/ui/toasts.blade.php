<div
    role="status"
    aria-live="polite"
    class="no-print pointer-events-none fixed inset-x-0 top-[max(0.75rem,env(safe-area-inset-top))] z-[1000] flex flex-col items-center gap-2 px-4 md:items-end"
    x-data
>
    <template x-for="toast in $store.toasts.items" :key="toast.id">
        <div
            class="pointer-events-auto flex w-full max-w-sm items-start gap-2 rounded-control border bg-surface p-3 shadow-md"
            :class="{
                'border-success': toast.tone === 'success',
                'border-danger': toast.tone === 'danger',
                'border-info': toast.tone === 'info',
            }"
            x-transition:enter="transition duration-200 ease-enter"
            x-transition:enter-start="-translate-y-2 opacity-0"
            x-transition:leave="transition duration-150 ease-exit"
            x-transition:leave-end="opacity-0"
        >
            <span x-show="toast.tone === 'success'" class="text-success"><x-ui.icon name="circle-check" /></span>
            <span x-show="toast.tone === 'danger'" class="text-danger"><x-ui.icon name="circle-alert" /></span>
            <span x-show="toast.tone === 'info'" class="text-info"><x-ui.icon name="info" /></span>
            <p class="min-w-0 flex-1 text-base" x-text="toast.message"></p>
            <button
                type="button"
                class="-m-2 inline-flex size-11 shrink-0 items-center justify-center rounded-control hover:bg-primary-soft"
                aria-label="Tutup pesan"
                x-on:click="$store.toasts.dismiss(toast.id)"
            >
                <x-ui.icon name="x" size="sm" />
            </button>
        </div>
    </template>
</div>
