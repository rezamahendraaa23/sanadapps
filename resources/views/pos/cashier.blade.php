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
        {{-- Kepala kasir: nama merchant dan denyut penjualan hari ini. --}}
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex min-w-0 items-center gap-3">
                <span class="inline-flex size-12 shrink-0 -rotate-6 items-center justify-center rounded-card bg-accent text-on-primary shadow-md"><x-ui.icon name="store" /></span>
                <div class="min-w-0">
                    @if ($merchants->count() > 1)
                        <label for="field-merchantUuid" class="sr-only">Merchant</label>
                        <select id="field-merchantUuid" wire:model.live="merchantUuid" class="font-display -ml-1 max-w-full cursor-pointer appearance-none rounded-control bg-transparent px-1 text-2xl font-semibold break-words hover:bg-primary-soft">
                            @foreach ($merchants as $option)
                                <option value="{{ $option->uuid }}">{{ $option->name }}</option>
                            @endforeach
                        </select>
                    @else
                        <h2 class="font-display text-2xl font-semibold break-words">{{ $merchant?->name }}</h2>
                    @endif
                    <p class="text-sm text-text-muted">Kasir: {{ auth()->user()->name }}</p>
                </div>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                @if ($shift)
                    <button type="button" x-on:click="$dispatch('open-modal', 'tutup-shift')" class="inline-flex min-h-11 items-center gap-2 rounded-full border border-success bg-success-soft px-4 text-sm font-semibold">
                        <span class="size-2 rounded-full bg-success motion-safe:animate-pulse" aria-hidden="true"></span>Shift sejak {{ $shift->opened_at->setTimezone(config('sanad.display_timezone'))->format('H.i') }}<span class="text-text-muted">, Tutup Shift</span>
                    </button>
                @else
                    <button type="button" x-on:click="$dispatch('open-modal', 'buka-shift')" class="inline-flex min-h-11 items-center gap-2 rounded-full border border-dashed border-text-muted px-4 text-sm font-semibold hover:border-primary hover:bg-primary-soft">
                        <x-ui.icon name="clock" size="sm" />Buka Shift
                    </button>
                @endif
                <span class="inline-flex items-center gap-2 rounded-full border border-border bg-surface px-4 py-2 text-sm">
                    <x-ui.icon name="receipt-text" size="sm" class="text-primary" /><span class="font-tabular font-semibold">{{ $todayCount }}</span> transaksi
                </span>
                <span class="inline-flex items-center gap-2 rounded-full bg-primary px-4 py-2 text-sm text-on-primary">
                    <x-ui.icon name="trending-up" size="sm" />Omzet hari ini <span class="font-tabular font-semibold">{{ Rupiah::format($todayTotal) }}</span>
                </span>
            </div>
        </div>

        <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_340px] xl:grid-cols-[minmax(0,1fr)_400px]">
            <section class="flex min-w-0 flex-col gap-4" aria-label="Produk">
                {{-- Bilah cari dan pindai jadi satu, seperti kolom cari aplikasi belanja. --}}
                <div class="grid gap-2 rounded-card border border-border bg-surface p-2 shadow-sm md:grid-cols-[minmax(0,1fr)_minmax(0,16rem)]">
                    <label class="flex min-h-12 items-center gap-2 rounded-control bg-bg px-3 focus-within:ring-2 focus-within:ring-primary">
                        <x-ui.icon name="search" class="shrink-0 text-text-muted" />
                        <span class="sr-only">Cari produk</span>
                        <input type="search" name="search" autocomplete="off" placeholder="Cari produk" wire:model.live.debounce.200ms="search" class="min-w-0 flex-1 bg-transparent text-base outline-none">
                    </label>
                    <div class="flex flex-col gap-1">
                        <form wire:submit="addBarcode" class="flex min-h-12 items-center gap-2 rounded-control bg-bg px-3 focus-within:ring-2 focus-within:ring-primary">
                            <x-ui.icon name="scan-barcode" class="shrink-0 text-text-muted" />
                            <label for="field-barcode" class="sr-only">Barcode</label>
                            <input id="field-barcode" name="barcode" autocomplete="off" inputmode="numeric" placeholder="Pindai barcode" wire:model="barcode" @error('barcode') aria-invalid="true" aria-describedby="barcode-error" @enderror class="min-w-0 flex-1 bg-transparent text-base outline-none">
                        </form>
                        @error('barcode')
                            <p id="barcode-error" role="alert" class="flex items-center gap-1 px-1 text-sm font-semibold text-danger"><x-ui.icon name="circle-alert" size="sm" />{{ $message }}</p>
                        @enderror
                    </div>
                    <div class="md:col-span-2">@include('pos.partials.camera-barcode', ['target' => 'barcode', 'then' => 'addBarcode'])</div>
                </div>

                @if ($categories->isNotEmpty())
                    <div class="-mx-1 flex gap-2 overflow-x-auto px-1 pb-1" role="group" aria-label="Kategori">
                        <button type="button" wire:click="pickCategory('')" aria-pressed="{{ $category === '' ? 'true' : 'false' }}" @class(['min-h-11 shrink-0 rounded-full px-4 text-sm font-semibold transition-colors', 'bg-text text-surface' => $category === '', 'border border-border bg-surface hover:border-primary' => $category !== ''])>Semua</button>
                        @foreach ($categories as $cat)
                            <button type="button" wire:key="kat-{{ $cat->uuid }}" wire:click="pickCategory('{{ $cat->uuid }}')" aria-pressed="{{ $category === $cat->uuid ? 'true' : 'false' }}" @class(['min-h-11 shrink-0 rounded-full px-4 text-sm font-semibold transition-colors', 'bg-text text-surface' => $category === $cat->uuid, 'border border-border bg-surface hover:border-primary' => $category !== $cat->uuid])>{{ $cat->name }}</button>
                        @endforeach
                    </div>
                @endif

                @if ($products->isEmpty())
                    <x-ui.card>
                        <x-ui.empty-state icon="package" :message="$search !== '' ? 'Produk tidak ditemukan.' : 'Belum ada produk di merchant ini.'" class="py-2" />
                    </x-ui.card>
                @else
                    <div class="grid grid-cols-[repeat(auto-fill,minmax(10.5rem,1fr))] gap-3">
                        @foreach ($products as $product)
                            @php
                                $qty = $cart[$product->uuid] ?? 0;
                                $tone = ['bg-primary-soft text-primary', 'bg-accent-soft text-accent', 'bg-info-soft text-info'][crc32($product->name) % 3];
                                $initials = collect(preg_split('/\s+/u', trim($product->name)) ?: [])->take(2)->map(fn ($word) => mb_strtoupper(mb_substr($word, 0, 1)))->implode('');
                            @endphp
                            <button type="button" wire:key="produk-{{ $product->uuid }}" wire:click="add('{{ $product->uuid }}')" @disabled($product->isOutOfStock())
                                @class(['group relative flex flex-col overflow-hidden rounded-card border bg-surface text-left shadow-sm transition-all duration-150 ease-out motion-safe:active:scale-95 disabled:cursor-not-allowed disabled:opacity-55', 'border-primary ring-2 ring-primary' => $qty > 0, 'border-border hover:-translate-y-0.5 hover:border-primary hover:shadow-md' => $qty === 0])>
                                <span @class(['relative flex aspect-[4/3] items-center justify-center overflow-hidden', $tone])>
                                    @if ($product->photo_path)
                                        <img src="{{ route('staff.merchants.product-photo', $product) }}" alt="" loading="lazy" class="size-full object-cover transition-transform duration-300 group-hover:scale-105">
                                    @else
                                        <span class="font-display text-hero font-semibold" aria-hidden="true">{{ $initials }}</span>
                                    @endif
                                    @if ($product->isOutOfStock())
                                        <span class="absolute top-2 left-2 rounded-full bg-danger px-2 py-0.5 text-xs font-semibold text-on-primary">Habis</span>
                                    @elseif ($product->isLowStock())
                                        <span class="absolute top-2 left-2 rounded-full bg-warning-soft px-2 py-0.5 text-xs font-semibold">Sisa {{ $product->stock }}</span>
                                    @endif
                                    @if ($qty > 0)
                                        <span class="font-tabular absolute top-2 right-2 inline-flex size-8 items-center justify-center rounded-full bg-primary text-sm font-semibold text-on-primary shadow-md">{{ $qty }}<span class="sr-only"> di keranjang</span></span>
                                    @endif
                                </span>
                                <span class="flex flex-1 flex-col gap-1 p-3">
                                    <span class="line-clamp-2 min-w-0 text-base leading-snug font-semibold break-words">{{ $product->name }}</span>
                                    <span class="font-display font-tabular mt-auto text-lg font-semibold text-primary">{{ Rupiah::format($product->price) }}</span>
                                </span>
                            </button>
                        @endforeach
                    </div>
                @endif
            </section>

            <div class="flex min-w-0 flex-col gap-4 lg:sticky lg:top-4 lg:self-start">
                {{-- Keranjang bergaya struk: panel gelap, sobekan, total besar. --}}
                <section aria-label="Keranjang" class="relative overflow-hidden rounded-card bg-primary text-on-primary shadow-lg">
                    <span class="pointer-events-none absolute -top-16 -right-16 size-48 rounded-full border-[1.5rem] border-on-primary/5" aria-hidden="true"></span>
                    <div class="relative flex items-center justify-between gap-2 px-5 pt-5">
                        <h2 class="font-display text-xl font-semibold">Keranjang</h2>
                        <span class="font-tabular rounded-full bg-on-primary/15 px-3 py-1 text-sm font-semibold">{{ $itemCount }} barang</span>
                    </div>

                    @if ($lines->isEmpty())
                        <div class="relative flex flex-col items-center gap-2 px-5 py-10 text-center">
                            <span class="inline-flex size-14 items-center justify-center rounded-full bg-on-primary/10"><x-ui.icon name="shopping-basket" /></span>
                            <p class="text-base">Pilih produk untuk mulai.</p>
                        </div>
                    @else
                        <ul class="relative mt-3 flex max-h-[45vh] flex-col overflow-y-auto px-5">
                            @foreach ($lines as $uuid => $line)
                                <li wire:key="baris-{{ $uuid }}" class="flex items-center gap-3 border-b border-on-primary/10 py-3 last:border-b-0">
                                    <span class="min-w-0 flex-1">
                                        <span class="block text-base font-semibold break-words">{{ $line['product']->name }}</span>
                                        <span class="font-tabular block text-sm opacity-75">{{ $line['qty'] }} x {{ Rupiah::format($line['product']->price) }}</span>
                                        @if ($line['discount'] > 0)
                                            <span class="mt-1 inline-flex items-center gap-1 rounded-full bg-accent-soft px-2 py-0.5 text-xs font-semibold text-text"><x-ui.icon name="tag" size="sm" />{{ $line['promo'] }}, hemat {{ Rupiah::format($line['discount']) }}</span>
                                        @endif
                                    </span>
                                    <span class="flex shrink-0 items-center rounded-full bg-on-primary/10 p-1">
                                        <button type="button" wire:click="decrease('{{ $uuid }}')" class="inline-flex size-11 items-center justify-center rounded-full hover:bg-on-primary/15" aria-label="Kurangi {{ $line['product']->name }}"><x-ui.icon name="minus" size="sm" /></button>
                                        <span class="font-tabular w-7 text-center font-semibold" aria-label="Jumlah">{{ $line['qty'] }}</span>
                                        <button type="button" wire:click="add('{{ $uuid }}')" class="inline-flex size-11 items-center justify-center rounded-full hover:bg-on-primary/15" aria-label="Tambah {{ $line['product']->name }}"><x-ui.icon name="plus" size="sm" /></button>
                                    </span>
                                </li>
                            @endforeach
                        </ul>

                        <div class="relative my-2 flex items-center" aria-hidden="true">
                            <span class="-ml-3 size-6 shrink-0 rounded-full bg-bg"></span>
                            <span class="flex-1 border-t-2 border-dashed border-on-primary/25"></span>
                            <span class="-mr-3 size-6 shrink-0 rounded-full bg-bg"></span>
                        </div>

                        <div class="relative px-5 pb-5">
                            @if ($discount > 0)
                                <p class="font-tabular mb-1 text-right text-sm font-semibold opacity-80">Hemat {{ Rupiah::format($discount) }}</p>
                            @endif
                            <div class="flex items-end justify-between gap-3">
                                <span class="text-sm font-semibold opacity-75">Total</span>
                                <span class="font-display font-tabular text-hero font-semibold">{{ Rupiah::format($total) }}</span>
                            </div>
                            <button type="button" wire:click="startPayment" wire:loading.attr="disabled" wire:target="startPayment" class="font-display mt-4 inline-flex min-h-16 w-full items-center justify-center gap-2 rounded-control bg-accent-soft text-lg font-semibold text-text shadow-md transition-transform hover:-translate-y-0.5 focus-visible:ring-4 focus-visible:ring-on-primary/40 motion-safe:active:scale-[0.98]">
                                <x-ui.icon name="nfc" />Bayar dengan Kartu atau QR
                            </button>
                            @if ($acceptsCash)
                                <button type="button" wire:click="startCash" class="mt-2 inline-flex min-h-12 w-full items-center justify-center gap-2 rounded-control border border-on-primary/30 font-semibold hover:bg-on-primary/10">
                                    <x-ui.icon name="banknote" />Bayar Tunai
                                </button>
                            @endif
                            <button type="button" wire:click="removeAll" class="mt-2 min-h-11 w-full rounded-control text-sm font-semibold opacity-80 hover:bg-on-primary/10 hover:opacity-100">Kosongkan</button>
                        </div>
                    @endif
                </section>

                <section aria-label="Transaksi hari ini" class="rounded-card border border-border bg-surface p-4 shadow-sm">
                    <h2 class="mb-1 flex items-center gap-2 text-base font-semibold"><x-ui.icon name="history" size="sm" class="text-text-muted" />Transaksi hari ini</h2>
                    @forelse ($recent as $order)
                        <div wire:key="order-{{ $order->uuid }}" class="flex items-center gap-3 border-b border-border py-2 last:border-b-0">
                            <span @class(['inline-flex size-9 shrink-0 items-center justify-center rounded-full text-sm font-semibold', 'bg-primary-soft text-primary' => ! $order->isCash(), 'bg-accent-soft text-accent' => $order->isCash()]) aria-hidden="true">@if ($order->isCash())<x-ui.icon name="banknote" size="sm" />@else{{ mb_strtoupper(mb_substr($order->buyerName(), 0, 1)) }}@endif</span>
                            <span class="min-w-0 flex-1">
                                <span class="block text-sm font-semibold break-words">{{ $order->buyerName() }}</span>
                                <span class="font-tabular block text-xs text-text-muted">{{ $order->receipt_no }}, {{ $order->created_at->setTimezone(config('sanad.display_timezone'))->format('H.i') }}</span>
                            </span>
                            <span class="shrink-0 text-right">
                                <span @class(['font-tabular block text-sm font-semibold', 'line-through text-text-muted' => $order->status === 'void'])>{{ Rupiah::format($order->total) }}</span>
                                <a href="{{ route('staff.pos.receipt', $order) }}" target="_blank" rel="noopener" class="inline-flex min-h-11 items-center px-1 text-xs font-semibold text-primary hover:underline">Struk<span class="sr-only"> {{ $order->receipt_no }}</span></a>
                                @if ($order->status === 'void')
                                    <span class="text-xs text-text-muted">Dibatalkan</span>
                                @elseif ($order->canBeVoidedBy(auth()->user()))
                                    <button type="button" wire:click="askVoid('{{ $order->uuid }}')" class="min-h-11 text-xs font-semibold text-primary hover:underline">Batalkan<span class="sr-only"> {{ $order->receipt_no }}</span></button>
                                @endif
                            </span>
                        </div>
                    @empty
                        <x-ui.empty-state icon="receipt-text" message="Belum ada transaksi hari ini." class="py-2" />
                    @endforelse
                </section>
            </div>
        </div>
    @endif

    @if ($stage === 'tap')
        <div role="dialog" aria-modal="true" aria-label="Tap kartu" class="fixed inset-0 z-100 flex flex-col items-center justify-center gap-5 overflow-y-auto bg-primary p-6 text-center text-on-primary">
            <p class="rounded-full bg-on-primary/15 px-4 py-1 text-sm font-semibold">{{ $itemCount }} barang</p>
            <p class="font-display font-tabular text-[clamp(3rem,8vw,6rem)] leading-none font-semibold">{{ Rupiah::format($total) }}</p>
            <span class="relative my-4 inline-flex size-36 items-center justify-center" aria-hidden="true">
                <span class="absolute inset-0 rounded-full border-4 border-on-primary/30 motion-safe:animate-ping"></span>
                <span class="absolute inset-4 rounded-full bg-on-primary/10"></span>
                <span class="relative inline-flex size-20 -rotate-6 items-center justify-center rounded-card bg-accent-soft text-text shadow-lg"><x-ui.icon name="nfc" size="xl" /></span>
            </span>
            <h2 class="font-display text-3xl font-semibold">Tempelkan kartu atau pindai QR</h2>
            <form wire:submit="submitScan" class="w-full max-w-sm" x-data x-init="$nextTick(() => $el.querySelector('input').focus())" x-on:click.window="$el.querySelector('input')?.focus({ preventScroll: true })">
                <label for="field-scan" class="text-sm font-semibold opacity-80">Hasil pindai</label>
                <input id="field-scan" type="password" autocomplete="off" wire:model="scan" class="mt-1 min-h-12 w-full rounded-control border border-on-primary/30 bg-on-primary/10 px-3 text-center text-base text-on-primary">
            </form>
            <div x-data="qrScanner" x-show="supported" class="flex w-full max-w-sm flex-col gap-2" x-on:qr-found="$wire.set('scan', $event.detail).then(() => $wire.submitScan())">
                <video x-ref="video" x-show="running" muted playsinline class="w-full rounded-control bg-bg" aria-label="Kamera untuk memindai QR"></video>
                <button type="button" x-on:click="toggle()" class="inline-flex min-h-12 items-center justify-center gap-2 rounded-control border border-on-primary/30 font-semibold hover:bg-on-primary/10"><x-ui.icon name="camera" /><span x-text="running ? 'Matikan Kamera' : 'Pindai QR dengan Kamera'">Pindai QR dengan Kamera</span></button>
                <p x-show="problem" x-text="problem" role="alert" class="text-sm font-semibold"></p>
            </div>
            <div wire:loading wire:target="submitScan" class="text-base font-semibold">Memproses…</div>
            <button type="button" wire:click="backToCart" class="min-h-11 rounded-control px-4 font-semibold opacity-80 hover:bg-on-primary/10 hover:opacity-100">Batal</button>
        </div>
    @elseif ($stage === 'pin')
        <div role="dialog" aria-modal="true" aria-label="Masukkan PIN" class="fixed inset-0 z-100 flex flex-col items-center justify-center gap-3 overflow-y-auto bg-primary p-6 text-center text-on-primary"
            x-data="{ pin: '', press(digit) { if (this.pin.length < 6) this.pin += digit }, send() { if (this.pin.length >= 4) { const value = this.pin; this.pin = ''; $wire.submitPin(value) } } }"
            x-on:keydown.window="if (/^[0-9]$/.test($event.key)) press($event.key); if ($event.key === 'Backspace') pin = pin.slice(0, -1); if ($event.key === 'Enter') send()">
            <h2 class="font-display text-2xl font-semibold">Masukkan PIN santri</h2>
            <p class="font-display font-tabular text-3xl font-semibold">{{ Rupiah::format($total) }}</p>
            @if ($pinMessage)
                <p role="alert" class="flex items-center gap-1 rounded-full bg-danger px-3 py-1 text-base font-semibold"><x-ui.icon name="circle-alert" />{{ $pinMessage }}</p>
            @endif
            <p class="font-tabular min-h-10 text-3xl tracking-[0.3em]" aria-live="polite"><span x-text="'•'.repeat(pin.length)"></span><span class="sr-only" x-text="pin.length + ' angka terisi'"></span></p>
            <div class="grid w-full max-w-xs grid-cols-3 gap-2">
                @foreach ([1, 2, 3, 4, 5, 6, 7, 8, 9] as $digit)
                    <button type="button" class="font-display min-h-16 rounded-card bg-on-primary/10 text-2xl font-semibold hover:bg-on-primary/20" x-on:click="press('{{ $digit }}')">{{ $digit }}</button>
                @endforeach
                <button type="button" class="min-h-16 rounded-card bg-on-primary/10 hover:bg-on-primary/20" aria-label="Hapus angka" x-on:click="pin = pin.slice(0, -1)"><x-ui.icon name="delete" class="mx-auto" /></button>
                <button type="button" class="font-display min-h-16 rounded-card bg-on-primary/10 text-2xl font-semibold hover:bg-on-primary/20" x-on:click="press('0')">0</button>
                <button type="button" class="min-h-16 rounded-card bg-accent-soft font-semibold text-text" wire:loading.attr="disabled" wire:target="submitPin" x-on:click="send()">Lanjut</button>
            </div>
            <button type="button" wire:click="backToCart" class="min-h-11 rounded-control px-4 font-semibold opacity-80 hover:bg-on-primary/10 hover:opacity-100">Batal</button>
        </div>
    @elseif ($stage === 'result' && $result)
        <div role="alertdialog" aria-modal="true" aria-label="Hasil pembayaran" @class(['fixed inset-0 z-100 flex flex-col items-center justify-center gap-4 overflow-y-auto p-6 text-center', 'bg-success-soft' => $result['ok'], 'bg-danger-soft' => ! $result['ok']])
            @if ($result['ok'] && ! isset($result['change'])) x-data x-init="setTimeout(() => $wire.backToCart(), 3000)" @endif>
            <span @class(['relative inline-flex size-28 items-center justify-center rounded-full text-on-primary shadow-lg', 'bg-success' => $result['ok'], 'bg-danger' => ! $result['ok']])>
                @if ($result['ok'])<span class="absolute inset-0 rounded-full bg-success opacity-40 motion-safe:animate-ping" aria-hidden="true"></span>@endif
                <x-ui.icon :name="$result['ok'] ? 'check' : 'x'" size="xl" class="relative" />
            </span>
            <h2 class="font-display text-hero font-semibold">{{ $result['ok'] ? 'Berhasil' : 'Ditolak' }}</h2>
            @if ($result['ok'])
                <p class="text-xl font-semibold">{{ $result['name'] }}</p>
            @endif
            <p @class(['text-2xl font-semibold', 'rounded-full bg-surface px-5 py-2 shadow-sm' => $result['ok']])>{{ $result['message'] }}</p>
            @if ($result['ok'])
                <div class="flex flex-wrap justify-center gap-2">
                    @if (! empty($result['receipt']))
                        <x-ui.button icon="printer" :href="route('staff.pos.receipt', $result['receipt'])" target="_blank">Cetak Struk</x-ui.button>
                    @endif
                    <x-ui.button variant="secondary" wire:click="backToCart">Transaksi Baru</x-ui.button>
                </div>
            @else
                <div class="flex flex-wrap justify-center gap-2">
                    <x-ui.button wire:click="backToTap">Coba Lagi</x-ui.button>
                    <x-ui.button variant="secondary" wire:click="backToCart">Kembali ke Keranjang</x-ui.button>
                </div>
            @endif
        </div>
    @endif

    <x-ui.modal name="buka-shift" title="Buka shift">
        <p class="mb-3 text-base">Hitung uang di laci sebelum mulai berjualan.</p>
        <x-ui.input-rupiah label="Modal awal di laci" name="openingCash" wire:model="openingCash" />
        <x-slot:footer>
            <x-ui.button variant="secondary" x-on:click="open = false">Batal</x-ui.button>
            <x-ui.button wire:click="openShift" wire:loading.attr="disabled" wire:target="openShift">Buka Shift</x-ui.button>
        </x-slot:footer>
    </x-ui.modal>

    @if ($shift && $shiftTotals)
        <x-ui.modal name="tutup-shift" title="Tutup shift">
            <dl class="mb-4 grid grid-cols-2 gap-3">
                <div class="rounded-control bg-bg p-3"><dt class="text-xs text-text-muted">Transaksi</dt><dd class="font-tabular text-lg font-semibold">{{ $shiftTotals['order_count'] }}</dd></div>
                <div class="rounded-control bg-bg p-3"><dt class="text-xs text-text-muted">Kartu dan QR</dt><dd class="font-tabular text-lg font-semibold">{{ Rupiah::format($shiftTotals['card_sales']) }}</dd></div>
                <div class="rounded-control bg-bg p-3"><dt class="text-xs text-text-muted">Penjualan tunai</dt><dd class="font-tabular text-lg font-semibold">{{ Rupiah::format($shiftTotals['cash_sales']) }}</dd></div>
                <div class="rounded-control bg-primary-soft p-3"><dt class="text-xs text-text-muted">Seharusnya di laci</dt><dd class="font-tabular text-lg font-semibold">{{ Rupiah::format($shiftTotals['expected_cash']) }}</dd></div>
            </dl>
            <div class="flex flex-col gap-3">
                <x-ui.input-rupiah label="Uang di laci sekarang" name="countedCash" required help="Hitung fisik. Selisih dicatat, tidak dibetulkan otomatis." wire:model="countedCash" />
                <x-ui.input label="Catatan" name="shiftNote" wire:model="shiftNote" />
            </div>
            <x-slot:footer>
                <x-ui.button variant="secondary" x-on:click="open = false">Batal</x-ui.button>
                <x-ui.button wire:click="closeShift" wire:loading.attr="disabled" wire:target="closeShift">Tutup Shift</x-ui.button>
            </x-slot:footer>
        </x-ui.modal>
    @endif

    @if ($acceptsCash)
        <x-ui.modal name="bayar-tunai" title="Bayar tunai">
            <div class="flex flex-col gap-3" x-data="{ total: {{ (int) $total }}, get paid() { return Number(String($wire.cashPaid ?? '').replace(/\D/g, '')) || 0 } }">
                <p class="font-display font-tabular text-center text-3xl font-semibold">{{ Rupiah::format($total) }}</p>
                <x-ui.input-rupiah label="Uang diterima" name="cashPaid" required wire:model.live="cashPaid" />
                <div class="flex flex-wrap gap-2" role="group" aria-label="Uang cepat">
                    <button type="button" class="min-h-11 rounded-full border border-border px-4 text-sm font-semibold hover:border-primary" x-on:click="$wire.cashPaid = String(total)">Uang pas</button>
                    @foreach ([10000, 20000, 50000, 100000] as $note)
                        @if ($note > $total)
                            <button type="button" class="font-tabular min-h-11 rounded-full border border-border px-4 text-sm font-semibold hover:border-primary" x-on:click="$wire.cashPaid = '{{ $note }}'">{{ Rupiah::format($note) }}</button>
                        @endif
                    @endforeach
                </div>
                <p class="flex items-center justify-between rounded-control bg-bg p-3 text-base font-semibold" aria-live="polite">
                    <span>Kembalian</span>
                    <span class="font-tabular text-xl" x-text="paid >= total ? 'Rp ' + (paid - total).toLocaleString('id-ID') : 'Uang kurang'"></span>
                </p>
                <x-ui.input label="Nama pembeli" name="customerName" help="Tidak wajib. Misalnya Ustadz Ahmad atau tamu." wire:model="customerName" />
            </div>
            <x-slot:footer>
                <x-ui.button variant="secondary" x-on:click="open = false">Batal</x-ui.button>
                <x-ui.button icon="banknote" wire:click="payCash" wire:loading.attr="disabled" wire:target="payCash">Terima Pembayaran</x-ui.button>
            </x-slot:footer>
        </x-ui.modal>
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
