<?php

namespace App\Modules\Wallet\Actions;

use App\Modules\Finance\Models\Payment;
use App\Modules\Wallet\Models\WalletTopup;
use App\Modules\Wallet\Support\WalletException;

class CreditTopup
{
    public function __construct(private WalletLedger $ledger) {}

    /**
     * Dipanggil ApplySettlement di dalam transaksinya, jadi saldo hanya bertambah bersama status lunas (WAL-02).
     */
    public function handle(Payment $payment, ?int $userId): void
    {
        $topup = WalletTopup::query()->where('payment_id', $payment->id)->lockForUpdate()->first();

        if ($topup === null) {
            throw new WalletException('Data top-up tidak ditemukan.');
        }

        $this->ledger->post($this->ledger->lock($topup->wallet_id), 'topup', 'credit', $topup->amount, [
            'ref_type' => 'payment',
            'ref_id' => $payment->id,
            'idempotency_key' => 'topup:'.$payment->id,
            'created_by' => $userId,
        ]);

        $topup->update(['status' => 'settled']);
    }

    /**
     * Pengembalian dana top-up mengurangi saldo; ditolak bila saldonya sudah terpakai.
     */
    public function reverse(Payment $payment, string $reason, ?int $userId): void
    {
        $topup = WalletTopup::query()->where('payment_id', $payment->id)->lockForUpdate()->firstOrFail();

        $this->ledger->post($this->ledger->lock($topup->wallet_id), 'refund', 'debit', $topup->amount, [
            'ref_type' => 'payment',
            'ref_id' => $payment->id,
            'idempotency_key' => 'topup-refund:'.$payment->id,
            'note' => $reason,
            'created_by' => $userId,
        ]);

        $topup->update(['status' => 'refunded']);
    }
}
