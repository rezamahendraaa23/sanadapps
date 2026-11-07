@php
    use App\Modules\Finance\Support\Rupiah;
    $steps = [
        ['Dibuat', true],
        [$payment->status === 'failed' ? 'Ditolak' : 'Menunggu verifikasi', in_array($payment->status, ['review', 'settlement', 'failed', 'refunded'], true)],
        ['Lunas', $payment->status === 'settlement'],
    ];
    $online = $payment->method === 'midtrans';
    $needsTransfer = ! $online && in_array($payment->status, ['pending', 'failed'], true);
    if ($online) {
        $steps[1] = ['Menunggu pembayaran', $payment->status !== 'failed' && $payment->status !== 'expired'];
    }
    $invoiceUuids = $payment->invoices->pluck('uuid')->join(',');
    $topupChild = $payment->topup?->wallet?->student;
    $retryUrl = $topupChild ? route('wali.wallet.topup', ['anak' => $topupChild->uuid]) : route('wali.checkout', ['tagihan' => $invoiceUuids]);
    $shareText = 'Kwitansi '.$payment->payment_no.' sebesar '.Rupiah::format($payment->amount).'. Periksa: '.route('receipts.verify', $payment);
@endphp

<div class="flex flex-col gap-4">
    <ol class="flex items-start justify-between gap-2 rounded-card border border-border bg-surface p-4" aria-label="Tahapan pembayaran">
        @foreach ($steps as [$label, $done])
            <li class="flex min-w-0 flex-1 flex-col items-center gap-1 text-center text-xs" @if ($done) aria-current="step" @endif>
                @if ($label === 'Ditolak')
                    <x-ui.icon name="circle-alert" class="text-danger" />
                @elseif ($done)
                    <x-ui.icon name="circle-check" class="text-success" />
                @else
                    <x-ui.icon name="circle-dashed" class="text-text-muted" />
                @endif
                <span @class(['font-semibold' => $done, 'text-text-muted' => ! $done])>{{ $label }}</span>
            </li>
        @endforeach
    </ol>

    @if ($online && in_array($payment->status, ['failed', 'expired'], true))
        <x-ui.banner tone="danger">{{ $payment->reject_reason ?? 'Pembayaran tidak berhasil.' }} {{ $topupChild ? 'Saldo belum bertambah.' : 'Tagihan belum terbayar.' }} Silakan bayar ulang.</x-ui.banner>
        <x-ui.button class="w-full" :href="$retryUrl" wire:navigate>Bayar Ulang</x-ui.button>
    @elseif ($online && $payment->status === 'pending')
        <div wire:poll.5s class="hatch flex items-start gap-2 rounded-card border border-border bg-surface p-4">
            <x-ui.icon name="clock" class="mt-0.5" />
            <p class="text-base">Menunggu pembayaran{{ $payment->expires_at ? ' sampai '.$payment->expires_at->setTimezone(config('sanad.display_timezone'))->translatedFormat('j M, H.i') : '' }}. Status berubah otomatis setelah pembayaran diterima.</p>
        </div>
    @elseif ($payment->status === 'failed')
        <x-ui.banner tone="danger">Bukti ditolak: {{ $payment->reject_reason }}. Unggah bukti yang benar di bawah.</x-ui.banner>
    @elseif ($payment->status === 'review')
        <div class="hatch flex items-start gap-2 rounded-card border border-border bg-surface p-4">
            <x-ui.icon name="hourglass" class="mt-0.5" />
            <p class="text-base">Bukti sudah diterima dan sedang diperiksa bendahara. Kami kabari setelah selesai.</p>
        </div>
    @endif

    <x-ui.ticket-card>
        <div class="flex flex-wrap items-start justify-between gap-2">
            <div class="min-w-0">
                <h2 class="font-tabular font-semibold whitespace-nowrap">{{ $payment->payment_no }}</h2>
                <p class="text-sm text-text-muted">{{ $payment->studentNames() }}</p>
            </div>
            <x-ui.payment-status :status="$payment->status" :method="$payment->method" class="shrink-0" />
        </div>
        @if ($needsTransfer)
            <div class="mt-3" x-data="{ copied: '' , copy(key, text) { navigator.clipboard?.writeText(text); this.copied = key; setTimeout(() => this.copied = '', 2000) } }">
                <p class="text-sm text-text-muted">{{ $payment->bankAccount?->bank_name }} a.n. {{ $payment->bankAccount?->account_holder }}</p>
                <button type="button" class="font-tabular flex min-h-11 w-full items-center justify-between gap-2 text-xl font-semibold" x-on:click="copy('rekening', @js(preg_replace('/\D/', '', (string) $payment->bankAccount?->account_no)))">
                    {{ $payment->bankAccount?->account_no }}
                    <span class="inline-flex items-center gap-1 text-sm font-normal text-primary"><x-ui.icon name="copy" size="sm" /><span x-text="copied === 'rekening' ? 'Tersalin' : 'Salin'"></span></span>
                </button>
                <p class="mt-2 text-sm text-text-muted">Jumlah transfer</p>
                <button type="button" class="font-display font-tabular flex min-h-11 w-full items-center justify-between gap-2 text-2xl font-semibold" x-on:click="copy('nominal', @js((string) $payment->transferAmount()))">
                    {{ Rupiah::format($payment->transferAmount()) }}
                    <span class="font-sans inline-flex items-center gap-1 text-sm font-normal text-primary"><x-ui.icon name="copy" size="sm" /><span x-text="copied === 'nominal' ? 'Tersalin' : 'Salin'"></span></span>
                </button>
                @if ($payment->unique_code)
                    <p class="mt-1 rounded-control bg-accent-soft p-2 text-sm">Transfer tepat sampai 3 angka terakhir ({{ $payment->unique_code }}). Kode ini memudahkan pencocokan dan tidak menambah tagihan.</p>
                @endif
            </div>
        @else
            <p class="font-display font-tabular mt-3 text-2xl font-semibold">{{ Rupiah::format($online ? $payment->gross_amount : $payment->amount) }}</p>
            @if ($payment->admin_fee)
                <p class="text-sm text-text-muted">Termasuk biaya admin {{ Rupiah::format($payment->admin_fee) }}</p>
            @endif
        @endif
        <x-slot:proof>
            @if ($online && $payment->status === 'pending')
                <div class="flex flex-col gap-2" x-data>
                    @if ($clientKey)
                        <script data-navigate-once src="{{ $snapUrl }}" data-client-key="{{ $clientKey }}"></script>
                    @endif
                    <x-ui.button class="w-full" x-on:click="window.snap ? window.snap.pay(@js($payment->gateway_payload['snap_token'] ?? ''), { onSuccess: () => $wire.$refresh(), onPending: () => $wire.$refresh(), onError: () => $wire.$refresh() }) : (window.location.href = @js($payment->gateway_payload['redirect_url'] ?? ''))">Lanjutkan Pembayaran</x-ui.button>
                    @if (! empty($payment->gateway_payload['redirect_url']))
                        <x-ui.button variant="secondary" class="w-full" :href="$payment->gateway_payload['redirect_url']" target="_blank" rel="noopener">Buka di Halaman Midtrans</x-ui.button>
                    @endif
                    <x-ui.button variant="tertiary" class="w-full" wire:click="cancelOnline" wire:confirm="Batalkan pembayaran ini dan pilih cara lain?">Ganti Cara Bayar</x-ui.button>
                </div>
            @elseif ($needsTransfer)
                <form wire:submit="submit" class="flex flex-col gap-3">
                    <div class="flex flex-col gap-1">
                        <label for="field-proof" class="text-sm font-semibold">Foto atau PDF bukti transfer</label>
                        <input id="field-proof" type="file" accept="image/jpeg,image/png,application/pdf" wire:model="proof" @error('proof') aria-invalid="true" @enderror class="min-h-11 w-full min-w-0 text-sm">
                        <p class="text-sm text-text-muted">JPG, PNG, atau PDF, maksimal 5 MB.</p>
                        <div wire:loading wire:target="proof" class="text-sm text-text-muted">Mengunggah…</div>
                        @error('proof')
                            <p role="alert" class="flex items-start gap-1 text-sm text-danger"><x-ui.icon name="circle-alert" size="sm" class="mt-0.5" />{{ $message }}</p>
                        @enderror
                    </div>
                    @if ($proof && ! $errors->has('proof') && str_starts_with((string) $proof->getMimeType(), 'image/'))
                        <img src="{{ $proof->temporaryUrl() }}" alt="Pratinjau bukti" class="max-h-60 w-full rounded-control border border-border object-contain">
                    @endif
                    <x-ui.button type="submit" class="w-full" wire:loading.attr="disabled" wire:target="submit,proof">
                        <span wire:loading.remove wire:target="submit">Kirim Bukti</span>
                        <span wire:loading wire:target="submit">Mengirim…</span>
                    </x-ui.button>
                </form>
            @elseif ($payment->status === 'settlement')
                <div class="flex flex-col gap-2">
                    <div class="flex justify-end"><x-ui.stamp>Lunas</x-ui.stamp></div>
                    <x-ui.button icon="file-down" class="w-full" :href="route('payments.receipt', $payment)">Unduh Kwitansi</x-ui.button>
                    <x-ui.button variant="secondary" icon="message-circle" class="w-full" :href="'https://wa.me/?text='.rawurlencode($shareText)" target="_blank" rel="noopener">Bagikan lewat WhatsApp</x-ui.button>
                </div>
            @else
                <p class="text-sm text-text-muted">Bukti tersimpan. Status berubah otomatis setelah diperiksa.</p>
            @endif
        </x-slot:proof>
    </x-ui.ticket-card>

    @if ($topupChild)
        <x-ui.button variant="tertiary" :href="route('wali.wallet', ['anak' => $topupChild->uuid])" wire:navigate>Kembali ke Dompet</x-ui.button>
    @else
        <x-ui.button variant="tertiary" :href="route('wali.invoices')" wire:navigate>Kembali ke Tagihan</x-ui.button>
    @endif
</div>
