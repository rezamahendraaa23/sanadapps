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

        <x-ui.card title="Target periode ini">
            @if ($progress['percent'] !== null)
                <p class="font-display font-tabular text-3xl font-semibold">{{ $progress['percent'] }}%</p>
                <div class="mt-2 h-3 overflow-hidden rounded-full bg-primary-soft" role="progressbar" aria-valuenow="{{ $progress['percent'] }}" aria-valuemin="0" aria-valuemax="100" aria-label="Progres target hafalan">
                    <div class="h-full rounded-full bg-primary" style="width: {{ $progress['percent'] }}%"></div>
                </div>
                <p class="mt-2 text-sm text-text-muted">{{ rtrim(rtrim(number_format($progress['pages'], 2, ',', ''), '0'), ',') }} halaman ziyadah{{ $progress['target_pages'] > 0 ? ' dari '.rtrim(rtrim(number_format($progress['target_pages'], 2, ',', ''), '0'), ',') : '' }}{{ $progress['target_juz'] > 0 ? ', target '.rtrim(rtrim(number_format($progress['target_juz'], 1, ',', ''), '0'), ',').' juz' : '' }}.</p>
            @else
                <x-ui.empty-state icon="target" message="Target belum diatur." class="py-2" />
            @endif
        </x-ui.card>

        <x-ui.card title="Peta hafalan">
            @include('tahfidz.partials.juz-map')
            <p class="mt-2 text-sm text-text-muted">Persentase dihitung dari surat yang selesai disetor pada juz awal surat itu.</p>
        </x-ui.card>

        <x-ui.card :title="$isGuardian ? 'Mutabaah hari ini' : 'Mutabaah saya hari ini'">
            @forelse ($items as $item)
                @php($log = $logs[$item->id] ?? null)
                @php($done = $log?->status === 'done')
                <div wire:key="m-{{ $item->uuid }}" class="flex min-h-11 items-center justify-between gap-3 border-b border-border py-2 last:border-b-0">
                    <span class="min-w-0 text-base break-words">{{ $item->name }}
                        @if ($log?->verified_by)
                            <span class="flex items-center gap-1 text-xs font-semibold text-success"><x-ui.icon name="circle-check" size="sm" />Diverifikasi</span>
                        @elseif ($done)
                            <span class="block text-xs text-text-muted">Menunggu verifikasi</span>
                        @endif
                    </span>
                    @if ($isGuardian)
                        <span class="shrink-0 text-sm font-semibold">{{ $done ? 'Sudah' : 'Belum' }}</span>
                    @else
                        <button type="button" role="switch" aria-checked="{{ $done ? 'true' : 'false' }}" aria-label="{{ $item->name }}" wire:click="toggle('{{ $item->uuid }}')" @disabled($log?->verified_by)
                            @class(['inline-flex min-h-11 min-w-20 shrink-0 items-center justify-center gap-1 rounded-full border px-3 text-sm font-semibold disabled:opacity-60', 'border-primary bg-primary text-on-primary' => $done, 'border-text-muted' => ! $done])>
                            @if ($done)<x-ui.icon name="check" size="sm" />Sudah @else Belum @endif
                        </button>
                    @endif
                </div>
            @empty
                <x-ui.empty-state icon="list-checks" message="Belum ada amalan dari pesantren." class="py-2" />
            @endforelse
        </x-ui.card>

        <x-ui.card title="Riwayat setoran">
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
