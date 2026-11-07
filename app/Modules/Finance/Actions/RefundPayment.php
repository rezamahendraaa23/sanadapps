<?php

namespace App\Modules\Finance\Actions;

use App\Modules\Finance\Models\CashCategory;
use App\Modules\Finance\Models\CashTransaction;
use App\Modules\Finance\Models\Invoice;
use App\Modules\Finance\Models\Payment;
use App\Modules\Wallet\Actions\CreditTopup;
use Illuminate\Support\Facades\DB;

class RefundPayment
{
    /**
     * Koreksi lewat catatan baru: terbayar invoice dikurangi dan kas keluar dicatat (FIN-16).
     */
    public function handle(Payment $payment, string $reason, ?int $userId, bool $recordCash = true): void
    {
        if (trim($reason) === '') {
            throw new FinanceException('Alasan pengembalian wajib diisi.');
        }

        DB::transaction(function () use ($payment, $reason, $userId, $recordCash): void {
            $locked = Payment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== 'settlement') {
                throw new FinanceException('Hanya pembayaran yang sudah lunas yang bisa dikembalikan.');
            }

            $allocations = $locked->allocations()->orderBy('invoice_id')->get();

            foreach (Invoice::query()->whereIn('id', $allocations->pluck('invoice_id'))->orderBy('id')->lockForUpdate()->get() as $invoice) {
                $invoice->paid_total -= $allocations->firstWhere('invoice_id', $invoice->id)->amount;
                $invoice->syncStatus();
                $invoice->save();
            }

            if ($locked->isTopup()) {
                app(CreditTopup::class)->reverse($locked, trim($reason), $userId);
            }

            $locked->update(['status' => 'refunded', 'refund_reason' => trim($reason)]);

            if (! $recordCash) {
                return;
            }

            CashTransaction::create([
                'type' => 'out',
                'category_id' => CashCategory::named(CashCategory::REFUND, 'out')->id,
                'amount' => $locked->amount,
                'transaction_date' => now(config('sanad.display_timezone'))->toDateString(),
                'description' => "Pengembalian {$locked->payment_no}: ".trim($reason),
                'source_type' => 'payment',
                'source_id' => $locked->id,
                'created_by' => $userId,
            ]);
        });

        $payment->refresh();
    }
}
