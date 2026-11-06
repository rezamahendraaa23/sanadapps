<div class="flex flex-col gap-4">
    <h1 class="font-display text-2xl font-semibold">Masuk</h1>

    @if (session('status'))
        <x-ui.banner tone="success">{{ session('status') }}</x-ui.banner>
    @endif

    <div role="tablist" aria-label="Masuk sebagai" class="grid auto-cols-fr grid-flow-col gap-1 rounded-control bg-bg p-1">
        @foreach ($tabs as $key => $label)
            <button
                type="button"
                role="tab"
                id="tab-{{ $key }}"
                aria-selected="{{ $tab === $key ? 'true' : 'false' }}"
                aria-controls="panel-masuk"
                wire:click="selectTab('{{ $key }}')"
                @class([
                    'min-h-11 rounded-control text-base font-semibold',
                    'bg-primary text-on-primary' => $tab === $key,
                    'text-text-muted hover:bg-primary-soft' => $tab !== $key,
                ])
            >{{ $label }}</button>
        @endforeach
    </div>

    <div id="panel-masuk" role="tabpanel" aria-labelledby="tab-{{ $tab }}">
        @if ($tab === 'wali')
            <form wire:submit="requestOtp" class="flex flex-col gap-4">
                <x-ui.input label="Nomor WhatsApp" name="phone" type="tel" inputmode="tel" autocomplete="tel" required wire:model="phone" help="Kode masuk dikirim lewat WhatsApp, jadi tidak perlu mengingat sandi." />
                <x-ui.button type="submit" class="w-full" wire:loading.attr="disabled" wire:target="requestOtp">
                    <span wire:loading.remove wire:target="requestOtp">Kirim Kode</span>
                    <span wire:loading wire:target="requestOtp">Mengirim…</span>
                </x-ui.button>
            </form>
        @else
            <form wire:submit="loginWithPassword" class="flex flex-col gap-4">
                @if ($tab === 'santri')
                    <x-ui.input label="NIS" name="identifier" inputmode="numeric" autocomplete="username" required wire:model="identifier" />
                @else
                    <x-ui.input label="Email" name="identifier" type="email" autocomplete="username" required wire:model="identifier" />
                @endif
                <x-ui.input-password label="Sandi" name="password" wire:model="password" />
                <label class="flex min-h-11 items-center gap-3 text-base">
                    <input type="checkbox" wire:model="remember" class="size-5 accent-primary">
                    Tetap masuk di perangkat ini
                </label>
                <x-ui.button type="submit" class="w-full" wire:loading.attr="disabled" wire:target="loginWithPassword">
                    <span wire:loading.remove wire:target="loginWithPassword">Masuk</span>
                    <span wire:loading wire:target="loginWithPassword">Memeriksa…</span>
                </x-ui.button>
                @if ($tab === 'staf')
                    <x-ui.button variant="tertiary" :href="route('password.request')">Lupa sandi?</x-ui.button>
                @else
                    <p class="text-center text-sm text-text-muted">Lupa sandi? Minta admin pesantren mengaturnya ulang.</p>
                @endif
            </form>
        @endif
    </div>
</div>
