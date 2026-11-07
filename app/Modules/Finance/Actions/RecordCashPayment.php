<?php

namespace App\Modules\Finance\Actions;

use App\Modules\Finance\Models\Invoice;
use App\Modules\Finance\Models\InvoiceItem;
use App\Modules\Finance\Models\Payment;
use App\Modules\Finance\Models\PaymentAllocation;
use App\Modules\Finance\Support\DocumentNumber;
use Illuminate\Support\Facades\DB;

class RecordCashPayment
{
    public function __construct(private ApplySettlement $settlement) {}

    /**
     * Uang dialokasikan ke tagihan tertua dulu. Sisa bayar hanya boleh jatuh di tagihan yang
     * semua jenisnya mengizinkan cicilan (PRD Sistem 8.4).
     *
     * @param  list<int>  $invoiceIds
     */
    public function handle(array $invoiceIds, int $amount, int $userId): Payment
    {
        if ($amount <= 0) {
            throw new FinanceException('Nominal harus lebih dari nol.');
        }

        return DB::transaction(function () use ($invoiceIds, $amount, $userId): Payment {
            $invoices = LockPayableInvoices::handle($invoiceIds)->sortBy(fn (Invoice $invoice) => [$invoice->due_date->toDateString(), $invoice->id]);
            $totalDue = $invoices->sum(fn (Invoice $invoice) => $invoice->outstanding());

            if ($amount > $totalDue) {
                throw new FinanceException('Nominal melebihi total tagihan yang dipilih.');
            }

            $remaining = $amount;
            $allocations = [];

            foreach ($invoices as $invoice) {
                if ($remaining === 0) {
                    break;
                }

                $portion = min($remaining, $invoice->outstanding());

                if ($portion < $invoice->outstanding() && ! $this->allowsPartial($invoice)) {
                    throw new FinanceException("Tagihan {$invoice->invoice_no} harus dibayar penuh.");
                }

                $allocations[$invoice->id] = $portion;
                $remaining -= $portion;
            }

            $payment = Payment::create([
                'payment_no' => DocumentNumber::next('KW'),
                'method' => 'cash',
                'amount' => $amount,
                'gross_amount' => $amount,
                'status' => 'pending',
                'created_by' => $userId,
            ]);

            foreach ($allocations as $invoiceId => $portion) {
                PaymentAllocation::create(['payment_id' => $payment->id, 'invoice_id' => $invoiceId, 'amount' => $portion]);
            }

            $this->settlement->handle($payment, $userId);

            return $payment->refresh();
        });
    }

    private function allowsPartial(Invoice $invoice): bool
    {
        return ! InvoiceItem::query()
            ->where('invoice_id', $invoice->id)
            ->where(fn ($query) => $query->whereNull('fee_type_id')->orWhereHas('feeType', fn ($feeType) => $feeType->where('allow_partial', false)))
            ->where('is_late_fee', false)
            ->exists();
    }
}
