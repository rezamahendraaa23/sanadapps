@php use App\Modules\Finance\Support\Rupiah; @endphp

<div class="flex flex-col gap-4">
    <div wire:offline class="flex items-center gap-2 rounded-control bg-danger-soft p-3 text-base font-semibold">
        <x-ui.icon name="wifi-off" />Tidak tersambung, transaksi belum diproses.
    </div>

    @if ($merchants->isEmpty())
        <x-ui.card>
            <x-ui.empty-state icon="store" message="Akun ini belum terhubung ke merchant. Minta admin menambahkan Anda sebagai kasir." />
        </x-ui.card>
    @else
        @if ($merchants->count() > 1)
            <div class="w-full md:max-w-xs">
                <x-ui.select label="Merchant" name="merchantUuid" :options="$merchants->pluck('name', 'uuid')->all()" wire:model.live="merchantUuid" />
            </div>
        @endif

        <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_320px] xl:grid-cols-[minmax(0,1fr)_380px]">
            <x-ui.card class="min-w-0">
                <div class="mb-4 grid gap-3 xl:grid-cols-2">
                    <x-ui.input label="Cari produk" name="search" type="search" autocomplete="off" wire:model.live.debounce.200ms="search" />
                    <form wire:submit="addBarcode">
                        <x-ui.input label="Barcode" name="barcode" autocomplete="off" inputmode="numeric" help="Pindai lalu otomatis masuk keranjang." wire:model="barcode" />
                    </form>
                </div>
                @if ($products->isEmpty())
                    <x-ui.empty-state icon="package" :message="$search !== '' ? 'Produk tidak ditemukan.' : 'Belum ada produk di merchant ini.'" class="py-2" />
                @else
                    <div class="grid grid-cols-[repeat(auto-fill,minmax(9.5rem,1fr))] gap-2">
                        @foreach ($products as $product)
                            <button type="button" wire:key="produk-{{ $product->uuid }}" wire:click="add('{{ $product->uuid }}')" class="flex min-h-20 flex-col justify-between gap-1 rounded-control border border-border bg-surface p-3 text-left hover:border-primary hover:bg-primary-soft">
                                <span class="min-w-0 text-base font-semibold break-words">{{ $product->name }}</span>
                                <span class="font-tabular text-sm whitespace-nowrap text-text-muted">{{ Rupiah::format($product->price) }}</span>
                            </button>
                        @endforeach
                    </div>
                @endif
            </x-ui.card>

            <div class="flex min-w-0 flex-col gap-4">
                <x-ui.card title="Keranjang">
                    @if ($lines->isEmpty())
                        <x-ui.empty-state icon="shopping-basket" message="Pilih produk untuk mulai." class="py-2" />
                    @else
                        <ul class="flex flex-col">
                            @foreach ($lines as $uuid => $line)
                                <li wire:key="baris-{{ $uuid }}" class="flex items-center gap-2 border-b border-border py-2 last:border-b-0">
                                    <span class="min-w-0 flex-1">
                                        <span class="block text-base break-words">{{ $line['product']->name }}</span>
                                        <span class="font-tabular block text-sm text-text-muted">{{ Rupiah::format($line['amount']) }}</span>
                                    </span>
                                    <span class="flex shrink-0 items-center gap-1">
                                        <button type="button" wire:click="decrease('{{ $uuid }}')" class="inline-flex size-11 items-center justify-center rounded-control border border-border hover:bg-primary-soft" aria-label="Kurangi {{ $line['product']->name }}"><x-ui.icon name="minus" /></button>
                                        <span class="font-tabular w-8 text-center font-semibold" aria-label="Jumlah">{{ $line['qty'] }}</span>
                                        <button type="button" wire:click="add('{{ $uuid }}')" class="inline-flex size-11 items-center justify-center rounded-control border border-border hover:bg-primary-soft" aria-label="Tambah {{ $line['product']->name }}"><x-ui.icon name="plus" /></button>
                                    </span>
                                </li>
                            @endforeach
                        </ul>
                        <div class="mt-3 flex items-end justify-between gap-3">
                            <span class="font-semibold">Total</span>
                            <span class="font-display font-tabular text-3xl font-semibold">{{ Rupiah::format($total) }}</span>
                        </div>
                        <x-ui.button icon="credit-card" class="mt-3 min-h-16 w-full text-lg" wire:click="startPayment" wire:loading.attr="disabled" wire:target="startPayment">Bayar dengan Kartu atau QR</x-ui.button>
                        <x-ui.button variant="tertiary" class="mt-1 w-full" wire:click="removeAll">Kosongkan</x-ui.button>
                    @endif
                </x-ui.card>

                <x-ui.card title="Transaksi hari ini">
                    @forelse ($recent as $order)
                        <div wire:key="order-{{ $order->uuid }}" class="flex items-center gap-2 border-b border-border py-2 last:border-b-0">
                            <span class="min-w-0 flex-1">
                                <span class="block text-base break-words">{{ $order->student->name }}</span>
                                <span class="font-tabular block text-xs text-text-muted">{{ $order->receipt_no }}, {{ $order->created_at->setTimezone(config('sanad.display_timezone'))->format('H.i') }}</span>
                            </span>
                            <span class="shrink-0 text-right">
                                <span @class(['font-tabular block font-semibold', 'line-through' => $order->status === 'void'])>{{ Rupiah::format($order->total) }}</span>
                                @if ($order->status === 'void')
                                    <span class="text-xs text-text-muted">Dibatalkan</span>
                                @elseif ($order->canBeVoidedBy(auth()->user()))
                                    <button type="button" wire:click="askVoid('{{ $order->uuid }}')" class="min-h-11 text-sm font-semibold text-primary hover:underline">Batalkan<span class="sr-only"> {{ $order->receipt_no }}</span></button>
                                @endif
                            </span>
                        </div>
                    @empty
                        <x-ui.empty-state icon="receipt-text" message="Belum ada transaksi hari ini." class="py-2" />
                    @endforelse
                </x-ui.card>
            </div>
        </div>
    @endif

    @if ($stage === 'tap')
        <div role="dialog" aria-modal="true" aria-label="Tap kartu" class="fixed inset-0 z-100 flex flex-col items-center justify-center gap-4 overflow-y-auto bg-surface p-6 text-center">
            <p class="text-base text-text-muted">Total</p>
            <p class="font-display font-tabular text-hero font-semibold">{{ Rupiah::format($total) }}</p>
            <x-ui.icon name="nfc" size="xl" class="motion-safe:animate-pulse text-primary" />
            <h2 class="font-display text-2xl font-semibold">Tap kartu atau pindai QR</h2>
            <form wire:submit="submitScan" class="w-full max-w-sm" x-data x-init="$nextTick(() => $el.querySelector('input').focus())" x-on:click.window="$el.querySelector('input')?.focus({ preventScroll: true })">
                <label for="field-scan" class="text-sm font-semibold">Hasil pindai</label>
                <input id="field-scan" type="password" autocomplete="off" wire:model="scan" class="mt-1 min-h-12 w-full rounded-control border border-text-muted bg-surface px-3 text-center text-base">
            </form>
            <div x-data="qrScanner" x-show="supported" class="flex w-full max-w-sm flex-col gap-2" x-on:qr-found="$wire.set('scan', $event.detail).then(() => $wire.submitScan())">
                <video x-ref="video" x-show="running" muted playsinline class="w-full rounded-control bg-bg" aria-label="Kamera untuk memindai QR"></video>
                <x-ui.button variant="secondary" icon="camera" x-on:click="toggle()"><span x-text="running ? 'Matikan Kamera' : 'Pindai QR dengan Kamera'">Pindai QR dengan Kamera</span></x-ui.button>
                <p x-show="problem" x-text="problem" role="alert" class="text-sm text-danger"></p>
            </div>
            <div wire:loading wire:target="submitScan" class="text-base font-semibold">Memproses…</div>
            <x-ui.button variant="tertiary" wire:click="backToCart">Batal</x-ui.button>
        </div>
    @elseif ($stage === 'pin')
        <div role="dialog" aria-modal="true" aria-label="Masukkan PIN" class="fixed inset-0 z-100 flex flex-col items-center justify-center gap-3 overflow-y-auto bg-surface p-6 text-center"
            x-data="{ pin: '', press(digit) { if (this.pin.length < 6) this.pin += digit }, send() { if (this.pin.length >= 4) { const value = this.pin; this.pin = ''; $wire.submitPin(value) } } }"
            x-on:keydown.window="if (/^[0-9]$/.test($event.key)) press($event.key); if ($event.key === 'Backspace') pin = pin.slice(0, -1); if ($event.key === 'Enter') send()">
            <h2 class="font-display text-2xl font-semibold">Masukkan PIN santri</h2>
            <p class="font-display font-tabular text-3xl font-semibold">{{ Rupiah::format($total) }}</p>
            @if ($pinMessage)
                <p role="alert" class="flex items-center gap-1 text-base font-semibold text-danger"><x-ui.icon name="circle-alert" />{{ $pinMessage }}</p>
            @endif
            <p class="font-tabular min-h-10 text-3xl tracking-[0.3em]" aria-live="polite"><span x-text="'•'.repeat(pin.length)"></span><span class="sr-only" x-text="pin.length + ' angka terisi'"></span></p>
            <div class="grid w-full max-w-xs grid-cols-3 gap-2">
                @foreach ([1, 2, 3, 4, 5, 6, 7, 8, 9] as $digit)
                    <button type="button" class="font-display min-h-16 rounded-control border border-border text-2xl font-semibold hover:bg-primary-soft" x-on:click="press('{{ $digit }}')">{{ $digit }}</button>
                @endforeach
                <button type="button" class="min-h-16 rounded-control border border-border hover:bg-primary-soft" aria-label="Hapus angka" x-on:click="pin = pin.slice(0, -1)"><x-ui.icon name="delete" class="mx-auto" /></button>
                <button type="button" class="font-display min-h-16 rounded-control border border-border text-2xl font-semibold hover:bg-primary-soft" x-on:click="press('0')">0</button>
                <button type="button" class="min-h-16 rounded-control bg-primary font-semibold text-on-primary" wire:loading.attr="disabled" wire:target="submitPin" x-on:click="send()">Lanjut</button>
            </div>
            <x-ui.button variant="tertiary" wire:click="backToCart">Batal</x-ui.button>
        </div>
    @elseif ($stage === 'result' && $result)
        <div role="alertdialog" aria-modal="true" aria-label="Hasil pembayaran" @class(['fixed inset-0 z-100 flex flex-col items-center justify-center gap-4 overflow-y-auto p-6 text-center', 'bg-success-soft' => $result['ok'], 'bg-danger-soft' => ! $result['ok']])
            @if ($result['ok']) x-data x-init="setTimeout(() => $wire.backToCart(), 3000)" @endif>
            <x-ui.icon :name="$result['ok'] ? 'circle-check' : 'circle-alert'" size="xl" @class(['text-success' => $result['ok'], 'text-danger' => ! $result['ok']]) />
            <h2 class="font-display text-hero font-semibold">{{ $result['ok'] ? 'Berhasil' : 'Ditolak' }}</h2>
            @if ($result['ok'])
                <p class="text-xl font-semibold">{{ $result['name'] }}</p>
            @endif
            <p class="text-2xl font-semibold">{{ $result['message'] }}</p>
            @if ($result['ok'])
                <x-ui.button variant="secondary" wire:click="backToCart">Transaksi Baru</x-ui.button>
            @else
                <div class="flex flex-wrap justify-center gap-2">
                    <x-ui.button wire:click="backToTap">Coba Lagi</x-ui.button>
                    <x-ui.button variant="secondary" wire:click="backToCart">Kembali ke Keranjang</x-ui.button>
                </div>
            @endif
        </div>
    @endif

    <x-ui.modal name="batal-transaksi" title="Batalkan transaksi?">
        <p class="mb-3 text-base">Saldo santri dikembalikan dan tercatat di buku besar.</p>
        <x-ui.input label="Alasan" name="voidReason" required wire:model="voidReason" />
        <x-slot:footer>
            <x-ui.button variant="secondary" x-on:click="open = false">Tidak Jadi</x-ui.button>
            <x-ui.button variant="danger" wire:click="confirmVoid" wire:loading.attr="disabled" wire:target="confirmVoid">Batalkan Transaksi</x-ui.button>
        </x-slot:footer>
    </x-ui.modal>
</div>
