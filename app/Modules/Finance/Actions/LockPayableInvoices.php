<?php

namespace App\Modules\Finance\Actions;

use App\Modules\Finance\Models\Invoice;
use App\Modules\Finance\Models\Payment;
use Illuminate\Support\Collection;

final class LockPayableInvoices
{
    /**
     * Mengunci invoice dan menolak yang sudah lunas atau sedang punya pembayaran berjalan,
     * supaya satu tagihan tidak dibayar dua kali lewat dua jalur. Harus dipanggil di dalam transaksi.
     *
     * @param  list<int>  $ids
     * @return Collection<int, Invoice>
     */
    public static function handle(array $ids): Collection
    {
        $invoices = Invoice::query()->whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get();

        if ($invoices->isEmpty()) {
            throw new FinanceException('Pilih minimal satu tagihan.');
        }

        foreach ($invoices as $invoice) {
            if (! $invoice->isOpen() || $invoice->outstanding() === 0) {
                throw new FinanceException("Tagihan {$invoice->invoice_no} sudah tidak perlu dibayar.");
            }
        }

        $busy = Payment::query()
            ->whereIn('status', ['pending', 'review'])
            ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->whereHas('invoices', fn ($query) => $query->whereIn('invoices.id', $ids))
            ->exists();

        if ($busy) {
            throw new FinanceException('Sudah ada pembayaran yang berjalan untuk tagihan ini. Selesaikan atau tunggu verifikasinya.');
        }

        return $invoices;
    }
}
