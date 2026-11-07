@php $tz = config('sanad.display_timezone'); @endphp

<div class="flex flex-col gap-4" wire:poll.5s>
    @include('leave.partials.gate-nav')

    <div wire:offline class="flex items-center gap-2 rounded-control bg-warning-soft p-3 text-base"><x-ui.icon name="wifi-off" />Tidak tersambung. Data terakhir pukul {{ $updatedAt }}.</div>

    <x-ui.hero-card label="Sedang di luar" :value="$total.' santri'">
        @if ($lateCount > 0) {{ $lateCount }} terlambat kembali. @else Tidak ada yang terlambat. @endif
    </x-ui.hero-card>

    <x-ui.card>
        <x-ui.page-toolbar>
            <div role="group" aria-label="Filter papan" class="flex flex-wrap gap-1">
                @foreach (['semua' => 'Semua', 'segera' => 'Segera kembali', 'terlambat' => 'Terlambat', 'pulang' => 'Izin pulang'] as $key => $label)
                    <button type="button" wire:key="f-{{ $key }}" wire:click="$set('filter', '{{ $key }}')" aria-pressed="{{ $filter === $key ? 'true' : 'false' }}" @class(['min-h-11 rounded-full border px-3 text-sm font-semibold', 'border-primary bg-primary-soft text-primary' => $filter === $key, 'border-border' => $filter !== $key])>{{ $label }}</button>
                @endforeach
            </div>
            <div class="w-full md:max-w-xs">
                <x-ui.input label="Catat manual: cari santri" name="search" type="search" autocomplete="off" wire:model.live.debounce.250ms="search" />
                @foreach ($results as $result)
                    <button type="button" wire:key="s-{{ $result->uuid }}" wire:click="select('{{ $result->uuid }}')" class="mt-1 flex min-h-11 w-full items-center justify-between gap-2 rounded-control px-2 text-left hover:bg-primary-soft"><span class="font-semibold">{{ $result->name }}</span><span class="text-sm text-text-muted">{{ $result->classroom?->name }}</span></button>
                @endforeach
            </div>
        </x-ui.page-toolbar>

        @if ($rows->isEmpty())
            <x-ui.empty-state icon="circle-check" message="Tidak ada santri di luar." class="py-2" />
        @else
            <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                @foreach ($rows as $leave)
                    <button type="button" wire:key="k-{{ $leave->uuid }}" wire:click="select('{{ $leave->student->uuid }}')" @class(['flex min-w-0 gap-3 rounded-card border p-3 text-left', 'hatch border-danger bg-danger-soft' => $leave->is_late, 'border-border bg-surface hover:bg-primary-soft' => ! $leave->is_late])>
                        @include('leave.partials.student-photo', ['student' => $leave->student])
                        <span class="min-w-0 flex-1">
                            <span class="block text-base font-semibold break-words">{{ $leave->student->name }}</span>
                            <span class="block text-sm text-text-muted">{{ $leave->student->classroom?->name }}, {{ $types[$leave->type] }}</span>
                            <span class="font-tabular block text-sm">Keluar {{ $leave->out_at?->setTimezone($tz)->format('H.i') }}, harus kembali {{ $leave->end_at->setTimezone($tz)->translatedFormat('j M, H.i') }}</span>
                            @if ($leave->is_late)
                                <span class="mt-1 inline-flex items-center gap-1 text-sm font-semibold text-danger"><x-ui.icon name="clock-alert" size="sm" />Terlambat {{ (int) $leave->end_at->diffInMinutes(now()) }} menit</span>
                            @else
                                <span class="block text-sm text-text-muted">Sisa {{ (int) now()->diffInMinutes($leave->end_at) }} menit</span>
                            @endif
                        </span>
                    </button>
                @endforeach
            </div>
        @endif
        <p class="mt-3 text-xs text-text-muted">Diperbarui pukul {{ $updatedAt }}.</p>
    </x-ui.card>

    <x-ui.modal name="catat-manual" :title="$student ? 'Catat: '.$student->name : 'Catat manual'">
        @if ($student)
            <div class="flex flex-col gap-3" x-data="{ darurat: false }">
                @if ($activeLeave)
                    <p class="text-base">{{ $types[$activeLeave->type] }}, {{ $activeLeave->isOutside() ? 'sedang di luar' : 'belum keluar' }}. Batas kembali {{ $activeLeave->end_at->setTimezone($tz)->translatedFormat('j M, H.i') }}.</p>
                @else
                    <x-ui.banner tone="warning">Tidak ada izin aktif. Hanya musyrif atau admin yang bisa mencatat keluar darurat.</x-ui.banner>
                @endif
                <x-ui.input label="Alasan" name="reason" required help="Wajib. Pencatatan manual ditinjau musyrif." wire:model="reason" />
                @if ($activeLeave?->type === 'pulang' && ! $activeLeave->isOutside())
                    <x-ui.input label="Nama penjemput" name="pickup_name" required wire:model="pickup_name" />
                    <x-ui.input label="Hubungan" name="pickup_relation" wire:model="pickup_relation" />
                @endif
                @can('gate.override')
                    @unless ($activeLeave?->isOutside())
                        <details class="rounded-control border border-border p-3">
                            <summary class="min-h-11 cursor-pointer content-center text-base font-semibold">Keluar darurat tanpa izin</summary>
                            <div class="mt-2 flex flex-col gap-3">
                                <x-ui.input label="Batas kembali" name="override_return" type="datetime-local" wire:model="override_return" />
                                <x-ui.button variant="danger" wire:click="override" wire:loading.attr="disabled" wire:target="override" wire:confirm="Catat keluar darurat? Wali dan musyrif langsung diberi tahu.">Catat Keluar Darurat</x-ui.button>
                            </div>
                        </details>
                    @endunless
                @endcan
            </div>
        @endif
        <x-slot:footer>
            <x-ui.button variant="secondary" x-on:click="open = false">Batal</x-ui.button>
            @if ($activeLeave)
                <x-ui.button wire:click="record" wire:loading.attr="disabled" wire:target="record">{{ $activeLeave->isOutside() ? 'Catat Masuk' : 'Catat Keluar' }}</x-ui.button>
            @endif
        </x-slot:footer>
    </x-ui.modal>
</div>
