<div class="flex flex-col gap-4">
    <x-ui.card>
        <div class="grid gap-3 md:grid-cols-3">
            <x-ui.input label="Cari nama atau nomor" name="search" type="search" autocomplete="off" wire:model.live.debounce.300ms="search" />
            <x-ui.select label="Status" name="status" placeholder="Semua" :options="$statuses" wire:model.live="status" />
            <x-ui.select label="Periode" name="period" placeholder="Semua" :options="$periods" wire:model.live="period" />
        </div>
    </x-ui.card>

    @if ($canManage && $selected !== [])
        <div class="flex flex-wrap items-center justify-between gap-2 rounded-card border border-border bg-surface p-3">
            <p class="text-base"><strong class="font-tabular">{{ count($selected) }}</strong> terpilih</p>
            <x-ui.button wire:click="startSelection" wire:confirm="Masukkan yang terpilih ke tahap seleksi?" wire:loading.attr="disabled" wire:target="startSelection">Masukkan ke Seleksi</x-ui.button>
        </div>
    @endif

    <x-ui.card>
        @if ($applicants->isEmpty())
            <x-ui.empty-state icon="user-plus" message="Belum ada pendaftar yang cocok." class="py-2" />
        @else
            <x-ui.table caption="Pendaftar">
                <thead><tr>@if ($canManage)<x-ui.th><span class="sr-only">Pilih</span></x-ui.th>@endif<x-ui.th>Nomor</x-ui.th><x-ui.th>Nama</x-ui.th><x-ui.th>Status</x-ui.th><x-ui.th numeric>Skor</x-ui.th></tr></thead>
                <tbody>
                    @foreach ($applicants as $applicant)
                        <tr wire:key="a-{{ $applicant->uuid }}">
                            @if ($canManage)
                                <x-ui.td label="Pilih"><input type="checkbox" value="{{ $applicant->uuid }}" wire:model.live="selected" class="size-5 accent-primary" aria-label="Pilih {{ $applicant->name }}"></x-ui.td>
                            @endif
                            <x-ui.td label="Nomor"><span class="font-tabular text-sm">{{ $applicant->reg_no }}</span></x-ui.td>
                            <x-ui.td label="Nama"><a href="{{ route('staff.ppdb.applicants.show', $applicant) }}" wire:navigate class="font-semibold text-primary underline break-words">{{ $applicant->name }}</a></x-ui.td>
                            <x-ui.td label="Status">{{ $statuses[$applicant->status] }}</x-ui.td>
                            <x-ui.td label="Skor" class="text-right"><span class="font-tabular font-semibold">{{ $applicant->total_score === null ? '-' : rtrim(rtrim(number_format($applicant->total_score, 2, ',', ''), '0'), ',') }}</span></x-ui.td>
                        </tr>
                    @endforeach
                </tbody>
            </x-ui.table>
        @endif
        <div class="mt-3">{{ $applicants->links() }}</div>
    </x-ui.card>
</div>
