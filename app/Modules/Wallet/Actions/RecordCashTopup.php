<?php

namespace App\Modules\Wallet\Actions;

use App\Modules\Core\Models\Student;
use App\Modules\Finance\Actions\ApplySettlement;
use App\Modules\Finance\Models\Payment;
use App\Modules\Finance\Support\DocumentNumber;
use App\Modules\Wallet\Models\Wallet;
use App\Modules\Wallet\Models\WalletTopup;
use App\Modules\Wallet\Support\WalletException;
use Illuminate\Support\Facades\DB;

class RecordCashTopup
{
    public function __construct(private ApplySettlement $settlement) {}

    public function handle(Student $student, int $amount, int $userId): Payment
    {
        if ($amount <= 0) {
            throw new WalletException('Isi nominal top-up.');
        }

        return DB::transaction(function () use ($student, $amount, $userId): Payment {
            $payment = Payment::create([
                'payment_no' => DocumentNumber::next('KW'),
                'purpose' => 'wallet_topup',
                'method' => 'cash',
                'amount' => $amount,
                'gross_amount' => $amount,
                'status' => 'pending',
                'created_by' => $userId,
            ]);

            WalletTopup::create(['wallet_id' => Wallet::forStudent($student)->id, 'payment_id' => $payment->id, 'amount' => $amount]);

            $this->settlement->handle($payment, $userId);

            return $payment->refresh();
        });
    }
}
