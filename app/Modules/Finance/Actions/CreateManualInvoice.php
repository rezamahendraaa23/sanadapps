<?php

namespace App\Modules\Finance\Actions;

use App\Modules\Core\Models\Student;
use App\Modules\Finance\Models\Invoice;
use App\Modules\Finance\Models\InvoiceItem;
use App\Modules\Finance\Support\DocumentNumber;
use Illuminate\Support\Facades\DB;

class CreateManualInvoice
{
    /**
     * @param  list<array{description: string, amount: int, fee_type_id?: int|null}>  $items
     */
    public function handle(Student $student, array $items, string $dueDate, ?int $userId = null): Invoice
    {
        return DB::transaction(function () use ($student, $items, $dueDate, $userId): Invoice {
            $invoice = Invoice::create([
                'invoice_no' => DocumentNumber::next('INV'),
                'student_id' => $student->id,
                'due_date' => $dueDate,
                'source' => 'manual',
                'created_by' => $userId,
            ]);

            foreach ($items as $item) {
                InvoiceItem::create([
                    'invoice_id' => $invoice->id,
                    'student_id' => $student->id,
                    'fee_type_id' => $item['fee_type_id'] ?? null,
                    'description' => $item['description'],
                    'qty' => 1,
                    'unit_price' => $item['amount'],
                    'amount' => $item['amount'],
                ]);
            }

            $invoice->refreshTotals();
            $invoice->save();

            return $invoice;
        });
    }
}
