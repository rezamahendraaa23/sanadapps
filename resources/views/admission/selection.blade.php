@php $tz = config('sanad.display_timezone'); @endphp

<div class="flex flex-col gap-4">
    <x-ui.card>
        <div class="grid gap-3 md:grid-cols-[1fr_auto] md:items-end">
            <x-ui.select label="Periode" name="period" placeholder="Terbaru" :options="$periods" wire:model.live="period" />
            @if ($current)
                <p class="text-base">Diterima <strong class="font-tabular">{{ $accepted }}</strong> dari kuota <strong class="font-tabular">{{ $current->quota }}</strong></p>
            @endif
        </div>
    </x-ui.card>

    @if ($current && $canManage)
        <x-ui.card title="Keputusan dan pengumuman">
            <div class="flex flex-col gap-3">
                <div><x-ui.button variant="secondary" wire:click="fillQuota" wire:confirm="Terima berurut skor sampai kuota penuh, sisanya jadi cadangan?" wire:loading.attr="disabled" wire:target="fillQuota">Terima Sesuai Kuota</x-ui.button></div>
                @if ($current->announced_at)
                    <p class="text-base">Sudah diumumkan {{ $current->announced_at->setTimezone($tz)->translatedFormat('j M Y, H.i') }}.</p>
                @elseif ($current->announce_at)
                    <p class="text-base">Dijadwalkan {{ $current->announce_at->setTimezone($tz)->translatedFormat('j M Y, H.i') }}.</p>
                @endif
                <div class="grid gap-3 md:grid-cols-[220px_auto_auto] md:items-start">
                    <x-ui.input label="Jadwalkan pengumuman" name="announce_at" type="datetime-local" wire:model="announce_at" />
                    <x-ui.button variant="secondary" class="md:mt-6" wire:click="schedule" wire:loading.attr="disabled" wire:target="schedule">Jadwalkan</x-ui.button>
                    <x-ui.button class="md:mt-6" wire:click="announceNow" wire:confirm="Umumkan hasil sekarang ke {{ $recipients }} pendaftar lewat WhatsApp?" wire:loading.attr="disabled" wire:target="announceNow">Umumkan Sekarang ({{ $recipients }})</x-ui.button>
                </div>
            </div>
        </x-ui.card>
    @endif

    <x-ui.card title="Peringkat">
        @if ($rows->isEmpty())
            <x-ui.empty-state icon="list-checks" message="Belum ada pendaftar dalam seleksi." class="py-2" />
        @else
            <x-ui.table caption="Peringkat pendaftar">
                <thead><tr><x-ui.th numeric>#</x-ui.th><x-ui.th>Nama</x-ui.th><x-ui.th>Status</x-ui.th><x-ui.th numeric>Skor</x-ui.th>@if ($canManage)<x-ui.th>Keputusan</x-ui.th>@endif</tr></thead>
                <tbody>
                    @foreach ($rows as $row)
                        <tr wire:key="r-{{ $row->uuid }}">
                            <x-ui.td label="#" class="text-right"><span class="font-tabular">{{ $loop->iteration }}</span></x-ui.td>
                            <x-ui.td label="Nama"><a href="{{ route('staff.ppdb.applicants.show', $row) }}" wire:navigate class="font-semibold text-primary underline break-words">{{ $row->name }}</a></x-ui.td>
                            <x-ui.td label="Status">{{ $statuses[$row->status] }}{{ in_array($row->status, ['accepted', 'waitlist', 'rejected'], true) && ! $row->announced_at ? ', belum diumumkan' : '' }}</x-ui.td>
                            <x-ui.td label="Skor" class="text-right"><span class="font-tabular font-semibold">{{ $row->total_score === null ? '-' : rtrim(rtrim(number_format($row->total_score, 2, ',', ''), '0'), ',') }}</span></x-ui.td>
                            @if ($canManage)
                                <x-ui.td label="Keputusan">
                                    <span class="flex flex-wrap gap-1">
                                        <x-ui.button variant="tertiary" wire:click="decide('{{ $row->uuid }}', 'accepted')">Terima<span class="sr-only"> {{ $row->name }}</span></x-ui.button>
                                        <x-ui.button variant="tertiary" wire:click="decide('{{ $row->uuid }}', 'waitlist')">Cadangan<span class="sr-only"> {{ $row->name }}</span></x-ui.button>
                                        <x-ui.button variant="tertiary" wire:click="decide('{{ $row->uuid }}', 'rejected')">Tolak<span class="sr-only"> {{ $row->name }}</span></x-ui.button>
                                    </span>
                                </x-ui.td>
                            @endif
                        </tr>
                    @endforeach
                </tbody>
            </x-ui.table>
        @endif
    </x-ui.card>
</div>
