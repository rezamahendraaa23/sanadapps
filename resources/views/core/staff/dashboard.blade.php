@php
    $doneCount = collect($steps)->where('done', true)->count();
    $percent = $steps ? (int) round($doneCount / count($steps) * 100) : 0;
@endphp

<div class="grid gap-4 min-[80rem]:grid-cols-[minmax(0,2fr)_minmax(0,1fr)]">
    <div class="flex min-w-0 flex-col gap-4">
        @if ($steps)
            <x-ui.card>
                <h2 class="rule-double pb-2 text-xl font-semibold">Setup awal pesantren</h2>
                <div class="mt-3 flex items-center gap-3">
                    <div class="h-2 flex-1 rounded-full bg-border" role="progressbar" aria-valuenow="{{ $percent }}" aria-valuemin="0" aria-valuemax="100" aria-label="Progres setup">
                        <div class="h-2 rounded-full bg-primary" style="width: {{ $percent }}%"></div>
                    </div>
                    <span class="font-tabular text-sm font-semibold">{{ $doneCount }} dari {{ count($steps) }}</span>
                </div>
                <ol class="mt-3 flex flex-col">
                    @foreach ($steps as $step)
                        <li wire:key="step-{{ $step['key'] }}" class="flex flex-wrap items-center gap-3 border-b border-border py-2 last:border-b-0">
                            @if ($step['done'])
                                <x-ui.icon name="circle-check" class="text-success" />
                            @else
                                <x-ui.icon name="circle-dashed" class="text-text-muted" />
                            @endif
                            <div class="min-w-0 flex-1 basis-48">
                                <a href="{{ route($step['route']) }}" wire:navigate class="font-semibold text-primary hover:underline">{{ $step['label'] }}</a>
                                <p class="text-sm text-text-muted">{{ $step['done'] ? 'Selesai.' : $step['hint'] }}</p>
                            </div>
                            @if (! $step['done'] && $step['can_mark'])
                                <x-ui.button variant="tertiary" wire:click="mark('{{ $step['key'] }}')">Tandai selesai<span class="sr-only">: {{ $step['label'] }}</span></x-ui.button>
                            @endif
                        </li>
                    @endforeach
                </ol>
                @if ($doneCount === count($steps))
                    <div class="mt-3 flex justify-end">
                        <x-ui.button variant="secondary" wire:click="dismissChecklist">Sembunyikan Daftar Ini</x-ui.button>
                    </div>
                @endif
            </x-ui.card>
        @endif

        <x-ui.card title="Perlu tindakan">
            @if ($pendingTransfers > 0)
                <a href="{{ route('staff.transfers.index') }}" wire:navigate class="hatch flex min-h-11 items-center justify-between gap-3 rounded-control border border-warning p-3 font-semibold">
                    <span class="flex items-center gap-2"><x-ui.icon name="hourglass" />Bukti transfer menunggu verifikasi</span>
                    <span class="font-tabular">{{ $pendingTransfers }}</span>
                </a>
            @else
                <x-ui.empty-state message="Tidak ada yang menunggu saat ini." class="py-2" />
            @endif
        </x-ui.card>
    </div>

    <div class="flex min-w-0 flex-col gap-4">
        @if ($heroIsFinance)
            <x-ui.hero-card label="Terbayar bulan ini" :value="\App\Modules\Finance\Support\Rupiah::format($finance['paidThisMonth'])">
                <x-slot:action>
                    <x-ui.button variant="on-primary" :href="route('staff.cash.index')" wire:navigate>Lihat Kas</x-ui.button>
                </x-slot:action>
            </x-ui.hero-card>
        @elseif ($activeStudents !== null)
            <x-ui.hero-card label="Santri aktif" :value="number_format($activeStudents, 0, ',', '.')">
                @if ($activeStudents === 0)
                    Belum ada santri.
                @endif
                <x-slot:action>
                    <x-ui.button variant="on-primary" :href="route('staff.students.index')" wire:navigate>Lihat Santri</x-ui.button>
                </x-slot:action>
            </x-ui.hero-card>
        @endif
        @if ($finance)
            <x-ui.card title="Keuangan">
                <dl class="flex flex-col gap-2">
                    <div class="flex justify-between gap-3"><dt class="text-sm text-text-muted">Tagihan bulan ini</dt><dd class="font-tabular font-semibold">{{ \App\Modules\Finance\Support\Rupiah::format($finance['billed']) }}</dd></div>
                    @unless ($heroIsFinance)
                        <div class="flex justify-between gap-3"><dt class="text-sm text-text-muted">Terbayar bulan ini</dt><dd class="font-tabular font-semibold">{{ \App\Modules\Finance\Support\Rupiah::format($finance['paidThisMonth']) }}</dd></div>
                    @endunless
                    <div class="flex justify-between gap-3"><dt class="text-sm text-text-muted">Tunggakan</dt><dd class="font-tabular font-semibold">{{ \App\Modules\Finance\Support\Rupiah::format($finance['arrears']) }}</dd></div>
                    @if (app(\App\Modules\Core\Support\ModuleRegistry::class)->enabled('wallet') && auth()->user()->can('wallet.manage'))
                        <div class="flex justify-between gap-3"><dt class="text-sm text-text-muted">Saldo dompet santri</dt><dd class="font-tabular font-semibold">{{ \App\Modules\Finance\Support\Rupiah::format((int) \App\Modules\Wallet\Models\Wallet::query()->sum('balance')) }}</dd></div>
                    @endif
                </dl>
            </x-ui.card>
        @endif
        @if ($classrooms !== null || $guardians !== null)
            <x-ui.card>
                <dl class="grid grid-cols-2 gap-3">
                    @if ($classrooms !== null)
                        <div><dt class="text-sm text-text-muted">Kelas</dt><dd class="font-display font-tabular text-2xl font-semibold">{{ $classrooms }}</dd></div>
                    @endif
                    @if ($guardians !== null)
                        <div><dt class="text-sm text-text-muted">Wali</dt><dd class="font-display font-tabular text-2xl font-semibold">{{ $guardians }}</dd></div>
                    @endif
                </dl>
            </x-ui.card>
        @endif
    </div>
</div>
