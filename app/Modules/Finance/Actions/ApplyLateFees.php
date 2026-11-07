<?php

namespace App\Modules\Finance\Actions;

use App\Modules\Finance\Models\FeeType;
use App\Modules\Finance\Models\Invoice;
use App\Modules\Finance\Models\InvoiceItem;
use App\Support\SchoolDate;
use Illuminate\Support\Facades\DB;

class ApplyLateFees
{
    /**
     * Job harian: menandai overdue dan menambahkan denda sekali per item, tidak dihitung ulang
     * tiap hari dan tidak kena diskon (PRD Sistem 8.2).
     *
     * @return array{overdue: int, late_fees: int}
     */
    public function handle(): array
    {
        $today = SchoolDate::today();
        $result = ['overdue' => 0, 'late_fees' => 0];

        $invoiceIds = Invoice::query()->open()->whereDate('due_date', '<', $today)->pluck('id');

        foreach ($invoiceIds as $id) {
            DB::transaction(function () use ($id, $today, &$result): void {
                $invoice = Invoice::query()->whereKey($id)->open()->lockForUpdate()->first();

                if ($invoice === null) {
                    return;
                }

                if ($invoice->status !== 'overdue') {
                    $result['overdue']++;
                }

                foreach ($invoice->items()->where('is_late_fee', false)->whereNotNull('fee_type_id')->get() as $item) {
                    $feeType = FeeType::find($item->fee_type_id);

                    if ($feeType?->late_fee_type === null || ! $feeType->late_fee_value) {
                        continue;
                    }

                    $dueDays = $invoice->due_date->diffInDays($today, false);

                    if ($dueDays <= (int) $feeType->late_fee_after_days) {
                        continue;
                    }

                    $amount = $feeType->late_fee_type === 'percent'
                        ? intdiv($item->amount * $feeType->late_fee_value, 100)
                        : $feeType->late_fee_value;

                    if ($amount <= 0) {
                        continue;
                    }

                    $late = InvoiceItem::query()->firstOrCreate(
                        ['student_id' => $invoice->student_id, 'fee_type_id' => $feeType->id, 'period_key' => 'denda:'.$item->id],
                        ['invoice_id' => $invoice->id, 'description' => 'Denda '.$item->description, 'qty' => 1, 'unit_price' => $amount, 'amount' => $amount, 'is_late_fee' => true],
                    );

                    if ($late->wasRecentlyCreated) {
                        $result['late_fees']++;
                    }
                }

                $invoice->refreshTotals();
                $invoice->save();
            });
        }

        return $result;
    }
}
