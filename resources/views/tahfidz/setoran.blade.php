@php
    $surahOptions = $surahs->mapWithKeys(fn ($surah) => [$surah->number => $surah->label().' ('.$surah->name_ar.')'])->all();
@endphp

<div class="flex flex-col gap-4">
    @can('tahfidz.manage')
        <div class="flex justify-end"><x-ui.button variant="secondary" :href="route('staff.tahfidz.admin')" wire:navigate>Target, Mutabaah, Rekap</x-ui.button></div>
    @endcan

    <x-ui.card>
        @if ($student)
            <div class="flex flex-wrap items-center justify-between gap-2">
                <p class="text-base font-semibold break-words">{{ $student->name }} <span class="font-tabular text-sm font-normal text-text-muted">{{ $student->nis }}</span></p>
                <x-ui.button variant="tertiary" wire:click="$set('studentUuid', '')">Ganti Santri</x-ui.button>
            </div>
            @if ($progress['percent'] !== null)
                <p class="mt-2 text-sm text-text-muted">Target periode ini {{ $progress['percent'] }}%, sudah {{ rtrim(rtrim(number_format($progress['pages'], 2, ',', ''), '0'), ',') }} halaman.</p>
            @endif
        @else
            <x-ui.input label="Cari nama atau NIS" name="search" type="search" autocomplete="off" wire:model.live.debounce.250ms="search" />
            @foreach ($results->concat($roster) as $result)
                <button type="button" wire:key="s-{{ $result->uuid }}" wire:click="pick('{{ $result->uuid }}')" class="mt-1 flex min-h-11 w-full items-center justify-between gap-2 rounded-control px-2 text-left hover:bg-primary-soft"><span class="font-semibold break-words">{{ $result->name }}</span><span class="font-tabular shrink-0 text-sm text-text-muted">{{ $result->nis }}</span></button>
            @endforeach
        @endif
    </x-ui.card>

    @if ($student)
        <x-ui.card title="Setoran baru">
            <form wire:submit="save" class="flex flex-col gap-3">
                <div class="grid grid-cols-2 gap-3">
                    <x-ui.select label="Jenis" name="type" :options="$types" wire:model="type" />
                    <x-ui.input label="Tanggal" name="record_date" type="date" wire:model="record_date" />
                </div>
                <x-ui.select label="Dari surat" name="surah_from" placeholder="Pilih surat" :options="$surahOptions" wire:model.live="surah_from" />
                @if ($fromSurah)
                    <x-ui.arabic class="block text-right text-xl">{{ $fromSurah->name_ar }}</x-ui.arabic>
                @endif
                <x-ui.input label="Dari ayat" name="ayah_from" inputmode="numeric" :help="$fromSurah ? 'Surat ini punya '.$fromSurah->ayah_count.' ayat.' : null" wire:model="ayah_from" />
                <x-ui.select label="Sampai surat" name="surah_to" placeholder="Pilih surat" :options="$surahOptions" wire:model.live="surah_to" />
                <x-ui.input label="Sampai ayat" name="ayah_to" inputmode="numeric" :help="$toSurah ? 'Surat ini punya '.$toSurah->ayah_count.' ayat.' : null" wire:model="ayah_to" />
                <div class="grid grid-cols-2 gap-3">
                    <x-ui.input label="Halaman" name="pages" inputmode="decimal" wire:model="pages" />
                    <x-ui.select label="Predikat" name="grade" :options="$grades" wire:model="grade" />
                </div>
                <x-ui.input label="Catatan" name="notes" wire:model="notes" />
                <div class="flex flex-col gap-2 md:flex-row">
                    <x-ui.button type="submit" wire:loading.attr="disabled" wire:target="save">Simpan</x-ui.button>
                    <x-ui.button variant="secondary" wire:click="save(true)" wire:loading.attr="disabled" wire:target="save">Simpan dan Santri Berikutnya</x-ui.button>
                </div>
            </form>
        </x-ui.card>

        <x-ui.card title="Riwayat">
            @forelse ($history as $record)
                <div wire:key="r-{{ $record->uuid }}" class="border-b border-border py-2 last:border-b-0">
                    <p class="text-sm text-text-muted">{{ $record->record_date->translatedFormat('j M Y') }}, {{ $types[$record->type] }}, {{ $grades[$record->grade] }}</p>
                    <p class="text-base break-words">{{ $record->surahFrom->name_latin }} {{ $record->ayah_from }} sampai {{ $record->surah_from === $record->surah_to ? '' : $record->surahTo->name_latin.' ' }}{{ $record->ayah_to }}</p>
                    <x-ui.arabic class="block text-right text-lg">{{ $record->surahFrom->name_ar }}{{ $record->surah_from === $record->surah_to ? '' : ' - '.$record->surahTo->name_ar }}</x-ui.arabic>
                </div>
            @empty
                <x-ui.empty-state icon="book-open" message="Belum ada setoran." class="py-2" />
            @endforelse
        </x-ui.card>
    @endif
</div>
