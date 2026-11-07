<?php

namespace App\Modules\Finance\Actions;

use App\Modules\Finance\Events\PaymentSettled;
use App\Modules\Finance\Models\CashCategory;
use App\Modules\Finance\Models\CashTransaction;
use App\Modules\Finance\Models\Invoice;
use App\Modules\Finance\Models\Payment;
use Illuminate\Support\Facades\DB;

class ApplySettlement
{
    /**
     * Satu-satunya jalan pembayaran menambah terbayar invoice. Baris dikunci dan status dicek
     * ulang, jadi verifikasi ganda atau webhook ganda tidak melunasi dua kali.
     *
     * @return bool true bila baru diterapkan, false bila sudah pernah.
     */
    public function handle(Payment $payment, ?int $verifiedBy = null, bool $allowClosed = false): bool
    {
        return DB::transaction(function () use ($payment, $verifiedBy, $allowClosed): bool {
            $locked = Payment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();

            if ($locked->status === 'settlement') {
                return false;
            }

            if (! in_array($locked->status, $allowClosed ? ['pending', 'review', 'failed', 'expired'] : ['pending', 'review'], true)) {
                throw new FinanceException('Pembayaran ini sudah ditutup dan tidak bisa dilunasi.');
            }

            $allocations = $locked->allocations()->orderBy('invoice_id')->get();
            $invoices = Invoice::query()->whereIn('id', $allocations->pluck('invoice_id'))->orderBy('id')->lockForUpdate()->get()->keyBy('id');

            foreach ($allocations as $allocation) {
                $invoice = $invoices[$allocation->invoice_id];

                if ($invoice->status === 'void') {
                    throw new FinanceException("Tagihan {$invoice->invoice_no} sudah dibatalkan.");
                }

                if ($allocation->amount > $invoice->outstanding()) {
                    throw new FinanceException("Tagihan {$invoice->invoice_no} sudah terbayar lewat cara lain.");
                }

                $invoice->paid_total += $allocation->amount;
                $invoice->syncStatus();
                $invoice->save();
            }

            $locked->update([
                'status' => 'settlement',
                'paid_at' => now(),
                'verified_by' => $verifiedBy,
                'verified_at' => $verifiedBy ? now() : null,
                'reject_reason' => null,
            ]);

            $this->recordCash($locked, $verifiedBy);
            $payment->refresh();

            PaymentSettled::dispatch($locked);

            return true;
        });
    }

    private function recordCash(Payment $payment, ?int $userId): void
    {
        $today = now(config('sanad.display_timezone'))->toDateString();

        $entries = [
            [CashCategory::INVOICE_PAYMENT, $payment->amount, "Pembayaran {$payment->payment_no}"],
            // Kode unik bukan pelunasan tagihan; selisihnya masuk kas sendiri (PRD Sistem 8.6).
            [CashCategory::UNIQUE_CODE, (int) $payment->unique_code, "Kode unik {$payment->payment_no}"],
        ];

        foreach ($entries as [$category, $amount, $description]) {
            if ($amount <= 0) {
                continue;
            }

            CashTransaction::create([
                'type' => 'in',
                'category_id' => CashCategory::named($category, 'in')->id,
                'amount' => $amount,
                'transaction_date' => $today,
                'description' => $description,
                'source_type' => 'payment',
                'source_id' => $payment->id,
                'created_by' => $userId,
            ]);
        }
    }
}
