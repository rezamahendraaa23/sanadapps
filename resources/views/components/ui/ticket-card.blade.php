@props(['proof'])

{{-- Hanya untuk objek yang memang bukti: kwitansi, QR izin, kartu santri, struk. --}}
<article {{ $attributes->class('ticket') }}>
    <div class="ticket-top rounded-t-card bg-surface p-4 pb-5 md:p-6 md:pb-6">
        {{ $slot }}
    </div>
    <div class="ticket-bottom rounded-b-card bg-surface px-4 pb-4 md:px-6 md:pb-6">
        <div class="border-t-2 border-dashed border-border pt-4">
            {{ $proof }}
        </div>
    </div>
</article>
