<?php

namespace App\Modules\Finance\Actions;

use App\Modules\Finance\Models\Invoice;
use App\Modules\Finance\Models\Payment;
use Illuminate\Support\Facades\DB;

class VoidInvoice
{
    public function handle(Invoice $invoice, string $reason): void
    {
        if (trim($reason) === '') {
            throw new FinanceException('Alasan pembatalan wajib diisi.');
        }

        DB::transaction(function () use ($invoice, $reason): void {
            $locked = Invoice::query()->whereKey($invoice->id)->lockForUpdate()->firstOrFail();

            if ($locked->status === 'void') {
                throw new FinanceException('Tagihan ini sudah dibatalkan.');
            }

            if ($locked->paid_total > 0) {
                throw new FinanceException('Tagihan yang sudah dibayar tidak bisa dibatalkan. Kembalikan pembayarannya dulu.');
            }

            if ($locked->payments()->whereIn('payments.status', ['pending', 'review'])->exists()) {
                throw new FinanceException('Masih ada pembayaran yang berjalan untuk tagihan ini.');
            }

            // Kunci periode dilepas agar tagihan yang dibatalkan bisa dibuat ulang saat generate berikutnya.
            $locked->items()->update(['period_key' => null]);
            $locked->update(['status' => 'void', 'void_reason' => trim($reason)]);
        });

        $invoice->refresh();
    }

    public static function openPaymentsExist(Invoice $invoice): bool
    {
        return Payment::query()->whereIn('status', ['pending', 'review'])->whereHas('invoices', fn ($query) => $query->whereKey($invoice->id))->exists();
    }
}
