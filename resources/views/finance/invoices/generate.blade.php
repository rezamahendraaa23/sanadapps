@php
    use App\Modules\Finance\Support\Rupiah;
    $summary = $result ?? $preview;
    $categories = ['monthly' => 'Bulanan', 'yearly' => 'Tahunan', 'one_time' => 'Sekali', 'custom' => 'Kustom'];
@endphp

<div class="mx-auto flex w-full max-w-[720px] flex-col gap-4">
    <x-ui.card>
        @if ($feeTypes->isEmpty())
            <x-ui.empty-state icon="tags" message="Belum ada jenis tagihan yang aktif.">
                <x-slot:action><x-ui.button :href="route('staff.settings.fee-types')" wire:navigate>Atur Jenis Tagihan</x-ui.button></x-slot:action>
            </x-ui.empty-state>
        @else
            <form wire:submit="runPreview" class="flex flex-col gap-4">
                <div class="grid gap-4 md:grid-cols-2">
                    <x-ui.input label="Periode" name="period" type="month" required wire:model.live="period" />
                    <x-ui.select label="Kelas" name="classroom" placeholder="Semua kelas" :options="$classrooms" wire:model.live="classroom" />
                </div>
                <fieldset class="flex flex-col gap-1">
                    <legend class="text-sm font-semibold">Jenis tagihan <span class="text-danger" aria-hidden="true">*</span></legend>
                    @foreach ($feeTypes as $type)
                        <label class="flex min-h-11 items-center gap-3 text-base">
                            <input type="checkbox" value="{{ $type->id }}" wire:model.live="feeTypeIds" class="size-5 accent-primary">
                            {{ $type->name }} <span class="text-sm text-text-muted">{{ $categories[$type->category] }}</span>
                        </label>
                    @endforeach
                    @error('feeTypeIds')
                        <p role="alert" class="flex items-start gap-1 text-sm text-danger"><x-ui.icon name="circle-alert" size="sm" class="mt-0.5" />{{ $message }}</p>
                    @enderror
                </fieldset>
                <div class="flex justify-end">
                    <x-ui.button type="submit" :variant="$preview ? 'secondary' : 'primary'" class="w-full md:w-auto" wire:loading.attr="disabled" wire:target="runPreview">
                        <span wire:loading.remove wire:target="runPreview">Pratinjau</span>
                        <span wire:loading wire:target="runPreview">Menghitung…</span>
                    </x-ui.button>
                </div>
            </form>
        @endif
    </x-ui.card>

    @if ($summary)
        <x-ui.card :title="$result ? 'Hasil generate' : 'Pratinjau'">
            <dl class="grid grid-cols-3 gap-3 text-center">
                <div><dt class="text-sm text-text-muted">Santri diperiksa</dt><dd class="font-display font-tabular text-2xl font-semibold">{{ $summary['students'] }}</dd></div>
                <div><dt class="text-sm text-text-muted">{{ $result ? 'Tagihan dibuat' : 'Tagihan baru' }}</dt><dd class="font-display font-tabular text-2xl font-semibold">{{ $summary['invoices'] }}</dd></div>
                <div><dt class="text-sm text-text-muted">Total</dt><dd class="font-display font-tabular text-xl font-semibold">{{ Rupiah::format($summary['total']) }}</dd></div>
            </dl>

            @if ($summary['skipped'])
                <h3 class="mt-4 text-base font-semibold">Dilewati ({{ count($summary['skipped']) }})</h3>
                <ul class="mt-2 max-h-60 overflow-y-auto rounded-control border border-border p-3 text-sm">
                    @foreach ($summary['skipped'] as $skip)
                        <li class="py-0.5">{{ $skip['student'] }}, {{ $skip['fee'] }}: <span class="text-text-muted">{{ $skip['reason'] }}</span></li>
                    @endforeach
                </ul>
            @endif

            <div class="mt-4 flex flex-col-reverse gap-2 md:flex-row md:justify-end">
                @if ($result)
                    <x-ui.button :href="route('staff.invoices.index')" wire:navigate>Lihat Tagihan</x-ui.button>
                @elseif ($summary['invoices'] > 0)
                    <x-ui.button class="w-full md:w-auto" x-data x-on:click="$dispatch('open-modal', 'konfirmasi-generate')">Generate {{ $summary['invoices'] }} Tagihan</x-ui.button>
                @else
                    <p class="text-base">Tidak ada tagihan baru untuk dibuat.</p>
                @endif
            </div>
        </x-ui.card>
    @endif

    <x-ui.modal name="konfirmasi-generate" title="Buat tagihan sekarang?">
        <p>{{ $preview['invoices'] ?? 0 }} tagihan senilai {{ Rupiah::format($preview['total'] ?? 0) }} akan dibuat dan langsung terlihat oleh wali.</p>
        <x-slot:footer>
            <x-ui.button variant="secondary" x-on:click="open = false">Batal</x-ui.button>
            <x-ui.button wire:click="generate" wire:loading.attr="disabled" wire:target="generate">Generate</x-ui.button>
        </x-slot:footer>
    </x-ui.modal>
</div>
