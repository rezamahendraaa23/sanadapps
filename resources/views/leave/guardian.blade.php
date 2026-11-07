@php $tz = config('sanad.display_timezone'); @endphp

<div class="flex flex-col gap-4">
    @if (! $child)
        <x-ui.card><x-ui.empty-state icon="users" message="Akun ini belum terhubung ke data santri. Hubungi admin pesantren." /></x-ui.card>
    @else
        @if ($children->count() > 1)
            <nav aria-label="Pilih anak" class="flex gap-2 overflow-x-auto pb-1">
                @foreach ($children as $item)
                    <button type="button" wire:key="anak-{{ $item->uuid }}" wire:click="$set('childUuid', '{{ $item->uuid }}')" @if ($item->is($child)) aria-current="true" @endif
                        @class(['inline-flex min-h-11 shrink-0 items-center rounded-full border px-4 text-sm font-semibold', 'border-primary bg-primary-soft text-primary' => $item->is($child), 'border-border' => ! $item->is($child)])>{{ $item->name }}</button>
                @endforeach
            </nav>
        @endif

        @if ($isGuardian)
            <x-ui.button icon="plus" class="w-full" x-data x-on:click="$dispatch('open-modal', 'ajukan-izin')">Ajukan Izin</x-ui.button>
        @endif

        <x-ui.card title="Izin {{ $child->name }}">
            @forelse ($leaves as $leave)
                <button type="button" wire:key="i-{{ $leave->uuid }}" wire:click="open('{{ $leave->uuid }}')" class="flex min-h-11 w-full flex-wrap items-center justify-between gap-2 border-b border-border py-2 text-left last:border-b-0">
                    <span class="min-w-0"><span class="block text-base font-semibold">{{ $types[$leave->type] }}</span><span class="block text-sm text-text-muted">{{ $leave->start_at->setTimezone($tz)->translatedFormat('j M, H.i') }} sampai {{ $leave->end_at->setTimezone($tz)->translatedFormat('j M, H.i') }}</span></span>
                    @include('leave.partials.status', ['leave' => $leave])
                </button>
            @empty
                <x-ui.empty-state icon="door-open" message="Belum ada izin." class="py-2" />
            @endforelse
        </x-ui.card>
    @endif

    @if ($isGuardian)
        <x-ui.modal name="ajukan-izin" title="Ajukan izin">
            <div class="flex flex-col gap-3">
                <x-ui.select label="Jenis" name="type" :options="$types" wire:model="type" />
                <x-ui.input label="Keluar" name="start_at" type="datetime-local" required wire:model="start_at" />
                <x-ui.input label="Kembali" name="end_at" type="datetime-local" required wire:model="end_at" />
                <x-ui.textarea label="Alasan" name="reason" required rows="2" wire:model="reason" />
            </div>
            <x-slot:footer>
                <x-ui.button variant="secondary" x-on:click="open = false">Batal</x-ui.button>
                <x-ui.button wire:click="submit" wire:loading.attr="disabled" wire:target="submit">Kirim Izin</x-ui.button>
            </x-slot:footer>
        </x-ui.modal>
    @endif

    <x-ui.modal name="detail-izin" title="Detail izin">
        @if ($detailLeave)
            <div class="flex flex-col gap-3">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <p class="text-base font-semibold">{{ $types[$detailLeave->type] }}</p>
                    @include('leave.partials.status', ['leave' => $detailLeave])
                </div>
                <p class="text-sm text-text-muted">{{ $detailLeave->start_at->setTimezone($tz)->translatedFormat('j M Y, H.i') }} sampai {{ $detailLeave->end_at->setTimezone($tz)->translatedFormat('j M Y, H.i') }}</p>
                <p class="text-base break-words">{{ $detailLeave->reason }}</p>
                @if ($detailLeave->reject_reason)
                    <x-ui.banner tone="danger">Ditolak: {{ $detailLeave->reject_reason }}</x-ui.banner>
                @endif
                @if ($qr)
                    <div class="mx-auto w-full max-w-[260px] bg-on-primary [&_svg]:h-auto [&_svg]:w-full" role="img" aria-label="QR izin untuk dipindai di gerbang">{!! $qr !!}</div>
                    <p class="text-center text-sm text-text-muted">Tunjukkan QR ini atau tap kartu di gerbang.</p>
                @endif
                @foreach ($detailLeave->logs->where('result', '!=', 'denied') as $log)
                    <p class="text-sm" wire:key="log-{{ $log->uuid }}">
                        {{ $log->direction === 'out' ? 'Keluar' : 'Masuk' }} {{ $log->occurred_at->setTimezone($tz)->translatedFormat('j M, H.i') }}@if ($log->pickup_name), dijemput {{ $log->pickup_name }}{{ $log->pickup_relation ? ' ('.$log->pickup_relation.')' : '' }}@endif
                    </p>
                @endforeach
                @if ($isGuardian && $detailLeave->status === 'pending')
                    <x-ui.button variant="secondary" wire:click="cancel('{{ $detailLeave->uuid }}')" wire:confirm="Batalkan izin ini?">Batalkan Izin</x-ui.button>
                @endif
            </div>
        @endif
    </x-ui.modal>
</div>
