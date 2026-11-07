@php $tz = config('sanad.display_timezone'); @endphp

<div class="flex flex-col gap-4">
    <x-ui.card>
        <x-ui.page-toolbar class="!mb-0">
            <div class="w-full md:max-w-xs"><x-ui.select label="Status" name="status" :options="$statuses" wire:model.live="status" /></div>
            <div class="flex flex-wrap gap-2">
                @if ($canSettings)
                    <x-ui.button variant="secondary" x-data x-on:click="$dispatch('open-modal', 'aturan-izin')">Aturan Izin</x-ui.button>
                @endif
                <x-ui.button icon="plus" x-data x-on:click="$dispatch('open-modal', 'buat-izin')">Buat Izin</x-ui.button>
            </div>
        </x-ui.page-toolbar>
    </x-ui.card>

    @if ($leaves->isEmpty())
        <x-ui.card><x-ui.empty-state icon="door-open" message="Tidak ada izin untuk status ini." class="py-2" /></x-ui.card>
    @else
        <div class="grid gap-3 lg:grid-cols-2">
            @foreach ($leaves as $leave)
                <x-ui.card wire:key="i-{{ $leave->uuid }}" class="flex min-w-0 flex-col gap-2">
                    <div class="flex flex-wrap items-start justify-between gap-2">
                        <div class="min-w-0"><h2 class="text-base font-semibold break-words">{{ $leave->student->name }}</h2><p class="text-sm text-text-muted">{{ $leave->student->classroom?->name }}, {{ $types[$leave->type] }}</p></div>
                        @include('leave.partials.status', ['leave' => $leave])
                    </div>
                    <p class="font-tabular text-sm">{{ $leave->start_at->setTimezone($tz)->translatedFormat('j M, H.i') }} sampai {{ $leave->end_at->setTimezone($tz)->translatedFormat('j M, H.i') }}</p>
                    <p class="text-base break-words">{{ $leave->reason }}</p>
                    @if ($leave->first_approved_by && $leave->status === 'pending')
                        <p class="text-sm text-text-muted">Sudah disetujui musyrif, menunggu pengelola.</p>
                    @endif
                    @if ($leave->status === 'pending' && $leave->canBeDecidedBy(auth()->user()))
                        <div class="mt-auto flex gap-2">
                            <x-ui.button class="flex-1" wire:click="approve('{{ $leave->uuid }}')" wire:loading.attr="disabled" wire:target="approve">Setujui<span class="sr-only"> {{ $leave->student->name }}</span></x-ui.button>
                            <x-ui.button variant="secondary" class="flex-1" wire:click="askReject('{{ $leave->uuid }}')">Tolak<span class="sr-only"> {{ $leave->student->name }}</span></x-ui.button>
                        </div>
                    @endif
                </x-ui.card>
            @endforeach
        </div>
        <div>{{ $leaves->links() }}</div>
    @endif

    @if ($toReview->isNotEmpty())
        <x-ui.card title="Tinjauan pencatatan manual gerbang">
            @foreach ($toReview as $log)
                <div wire:key="t-{{ $log->uuid }}" class="flex flex-wrap items-center justify-between gap-2 border-b border-border py-2 last:border-b-0">
                    <span class="min-w-0"><span class="block text-base break-words">{{ $log->student?->name }}, {{ $log->direction === 'out' ? 'keluar' : 'masuk' }} {{ $log->occurred_at->setTimezone($tz)->translatedFormat('j M, H.i') }}</span><span class="block text-sm break-words text-text-muted">{{ $log->recorder?->name }}: {{ $log->note }}</span></span>
                    <x-ui.button variant="tertiary" wire:click="markReviewed('{{ $log->uuid }}')">Tandai Ditinjau<span class="sr-only"> {{ $log->student?->name }}</span></x-ui.button>
                </div>
            @endforeach
        </x-ui.card>
    @endif

    <x-ui.modal name="tolak-izin" title="Tolak izin">
        <x-ui.input label="Alasan" name="rejectReason" required wire:model="rejectReason" />
        <x-slot:footer>
            <x-ui.button variant="secondary" x-on:click="open = false">Batal</x-ui.button>
            <x-ui.button variant="danger" wire:click="reject" wire:loading.attr="disabled" wire:target="reject">Tolak Izin</x-ui.button>
        </x-slot:footer>
    </x-ui.modal>

    <x-ui.modal name="buat-izin" title="Buat izin">
        <div class="flex flex-col gap-3">
            @if ($newStudentModel)
                <p class="text-base">Untuk <strong>{{ $newStudentModel->name }}</strong></p>
            @else
                <div>
                    <x-ui.input label="Cari santri" name="search" type="search" autocomplete="off" wire:model.live.debounce.250ms="search" />
                    @foreach ($results as $result)
                        <button type="button" wire:key="s-{{ $result->uuid }}" wire:click="pickStudent('{{ $result->uuid }}')" class="mt-1 flex min-h-11 w-full items-center justify-between gap-2 rounded-control px-2 text-left hover:bg-primary-soft"><span class="font-semibold">{{ $result->name }}</span><span class="font-tabular text-sm text-text-muted">{{ $result->nis }}</span></button>
                    @endforeach
                </div>
            @endif
            <x-ui.select label="Jenis" name="type" :options="$types" wire:model="type" />
            <x-ui.input label="Keluar" name="start_at" type="datetime-local" required wire:model="start_at" />
            <x-ui.input label="Kembali" name="end_at" type="datetime-local" required wire:model="end_at" />
            <x-ui.textarea label="Alasan" name="reason" required rows="2" wire:model="reason" />
        </div>
        <x-slot:footer>
            <x-ui.button variant="secondary" x-on:click="open = false">Batal</x-ui.button>
            <x-ui.button wire:click="submit" wire:loading.attr="disabled" wire:target="submit">Simpan</x-ui.button>
        </x-slot:footer>
    </x-ui.modal>

    @if ($canSettings)
        <x-ui.modal name="aturan-izin" title="Aturan izin">
            <div class="flex flex-col gap-3">
                <x-ui.input label="Toleransi terlambat kembali (menit)" name="return_tolerance" inputmode="numeric" required wire:model="return_tolerance" />
                <x-ui.input label="Boleh keluar lebih awal (menit)" name="exit_tolerance" inputmode="numeric" required wire:model="exit_tolerance" />
                <label class="flex min-h-11 items-center gap-3 text-base"><input type="checkbox" wire:model="two_level" class="size-5 accent-primary">Persetujuan dua tingkat: musyrif lalu pengelola</label>
            </div>
            <x-slot:footer>
                <x-ui.button variant="secondary" x-on:click="open = false">Batal</x-ui.button>
                <x-ui.button wire:click="saveSettings" wire:loading.attr="disabled" wire:target="saveSettings">Simpan</x-ui.button>
            </x-slot:footer>
        </x-ui.modal>
    @endif
</div>
