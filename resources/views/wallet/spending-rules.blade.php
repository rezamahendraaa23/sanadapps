@php use App\Modules\Finance\Support\Rupiah; @endphp

<form wire:submit="save" wire:confirm="Simpan aturan belanja? Langsung berlaku di kasir." class="flex flex-col gap-4">
    <x-ui.card title="Batas harian {{ $child->name }}">
        <x-ui.input-rupiah label="Total semua merchant" name="daily_limit" help="Kosongkan bila tanpa limit." wire:model="daily_limit" />
    </x-ui.card>

    <x-ui.card title="Per merchant">
        @forelse ($merchants as $merchant)
            <fieldset wire:key="m-{{ $merchant->uuid }}" class="border-b border-border py-3 first:pt-0 last:border-b-0 last:pb-0">
                <legend class="sr-only">{{ $merchant->name }}</legend>
                <p class="text-base font-semibold break-words">{{ $merchant->name }}</p>
                <div class="mt-2 flex flex-col gap-2">
                    <x-ui.input-rupiah label="Batas per hari" :name="'merchant_'.$merchant->id" wire:model="merchantRules.{{ $merchant->uuid }}.limit" />
                    <label class="flex min-h-11 items-center gap-3 text-base"><input type="checkbox" wire:model="merchantRules.{{ $merchant->uuid }}.blocked" class="size-5 accent-primary">Larang belanja di sini</label>
                </div>
            </fieldset>
        @empty
            <x-ui.empty-state icon="store" message="Belum ada merchant aktif." class="py-2" />
        @endforelse
    </x-ui.card>

    @if ($categories->isNotEmpty())
        <x-ui.card title="Per kategori">
            @foreach ($categories as $category)
                <fieldset wire:key="c-{{ $category->uuid }}" class="border-b border-border py-3 first:pt-0 last:border-b-0 last:pb-0">
                    <legend class="sr-only">{{ $category->name }}</legend>
                    <p class="text-base font-semibold break-words">{{ $category->name }}</p>
                    <div class="mt-2 flex flex-col gap-2">
                        <x-ui.input-rupiah label="Batas per hari" :name="'category_'.$category->id" wire:model="categoryRules.{{ $category->uuid }}.limit" />
                        <label class="flex min-h-11 items-center gap-3 text-base"><input type="checkbox" wire:model="categoryRules.{{ $category->uuid }}.blocked" class="size-5 accent-primary">Larang kategori ini</label>
                    </div>
                </fieldset>
            @endforeach
        </x-ui.card>
    @endif

    <x-ui.button type="submit" class="w-full" wire:loading.attr="disabled" wire:target="save">Simpan Aturan</x-ui.button>

    <x-ui.card title="Belanja bulan ini">
        @forelse ($monthly as $row)
            <div class="flex justify-between gap-3 border-b border-border py-2 last:border-b-0 text-base">
                <span class="min-w-0 break-words">{{ $row->merchant?->name }} <span class="text-sm text-text-muted">({{ $row->orders }} kali)</span></span>
                <span class="font-tabular shrink-0">{{ Rupiah::format((int) $row->total) }}</span>
            </div>
        @empty
            <x-ui.empty-state icon="shopping-basket" message="Belum ada belanja bulan ini." class="py-2" />
        @endforelse
    </x-ui.card>

    <x-ui.button variant="tertiary" :href="route('wali.wallet', ['anak' => $child->uuid])" wire:navigate>Kembali ke Dompet</x-ui.button>
</form>
