<?php

namespace App\Modules\Finance\Actions;

use App\Modules\Core\Models\Module;
use App\Modules\Finance\Models\BankAccount;
use App\Modules\Finance\Models\Invoice;
use App\Modules\Finance\Models\Payment;
use App\Modules\Finance\Models\PaymentAllocation;
use App\Modules\Finance\Support\DocumentNumber;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CreateManualPayment
{
    /**
     * @param  Collection<int, Invoice>  $invoices  Sudah lolos pemeriksaan kepemilikan.
     */
    public function handle(Collection $invoices, BankAccount $account, ?int $guardianId, ?int $userId): Payment
    {
        return DB::transaction(function () use ($invoices, $account, $guardianId, $userId): Payment {
            $locked = LockPayableInvoices::handle($invoices->pluck('id')->all());
            $amount = $locked->sum(fn (Invoice $invoice) => $invoice->outstanding());

            $payment = Payment::create([
                'payment_no' => DocumentNumber::next('KW'),
                'payer_guardian_id' => $guardianId,
                'method' => 'manual_transfer',
                'amount' => $amount,
                'unique_code' => $this->uniqueCode(),
                'gross_amount' => $amount,
                'status' => 'pending',
                'bank_account_id' => $account->id,
                'created_by' => $userId,
            ]);

            foreach ($locked as $invoice) {
                PaymentAllocation::create(['payment_id' => $payment->id, 'invoice_id' => $invoice->id, 'amount' => $invoice->outstanding()]);
            }

            return $payment;
        });
    }

    /**
     * Kode unik 3 digit bila diaktifkan; dihindari yang sedang dipakai agar mutasi mudah dicocokkan.
     */
    private function uniqueCode(): ?int
    {
        $settings = Module::query()->where('module_key', 'payment_manual')->value('settings') ?? [];

        if (empty($settings['unique_code'])) {
            return null;
        }

        $used = Payment::query()->whereIn('status', ['pending', 'review'])->whereNotNull('unique_code')->pluck('unique_code')->all();
        $free = array_values(array_diff(range(1, 999), $used));

        return $free === [] ? random_int(1, 999) : $free[array_rand($free)];
    }
}
