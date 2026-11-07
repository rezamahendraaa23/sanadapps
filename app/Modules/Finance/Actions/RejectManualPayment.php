<?php

namespace App\Modules\Finance\Actions;

use App\Modules\Finance\Events\ProofRejected;
use App\Modules\Finance\Models\Payment;
use Illuminate\Support\Facades\DB;

class RejectManualPayment
{
    public function handle(Payment $payment, string $reason, int $userId): void
    {
        if (trim($reason) === '') {
            throw new FinanceException('Alasan penolakan wajib diisi.');
        }

        DB::transaction(function () use ($payment, $reason, $userId): void {
            $locked = Payment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== 'review') {
                throw new FinanceException('Pembayaran ini sudah diproses.');
            }

            $locked->update(['status' => 'failed', 'reject_reason' => trim($reason), 'verified_by' => $userId, 'verified_at' => now()]);

            ProofRejected::dispatch($locked);
        });

        $payment->refresh();
    }
}
