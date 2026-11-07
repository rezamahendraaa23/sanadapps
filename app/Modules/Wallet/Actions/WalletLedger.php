<?php

namespace App\Modules\Wallet\Actions;

use App\Modules\Wallet\Models\Wallet;
use App\Modules\Wallet\Models\WalletTransaction;
use App\Modules\Wallet\Support\WalletException;
use Illuminate\Support\Facades\DB;
use LogicException;

class WalletLedger
{
    /**
     * Mengunci baris dompet. Harus di dalam transaksi; urutan kunci selalu kartu lalu dompet lalu merchant.
     */
    public function lock(int $walletId): Wallet
    {
        return Wallet::query()->whereKey($walletId)->lockForUpdate()->firstOrFail();
    }

    /**
     * Satu-satunya jalan saldo berubah. Saldo dan baris buku besar berubah bersama.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function post(Wallet $locked, string $type, string $direction, int $amount, array $attributes = []): WalletTransaction
    {
        if (DB::transactionLevel() === 0) {
            throw new LogicException('Mutasi dompet wajib di dalam transaksi.');
        }

        if ($amount <= 0) {
            throw new WalletException('Nominal harus lebih dari nol.');
        }

        $balance = $direction === 'credit' ? $locked->balance + $amount : $locked->balance - $amount;

        if ($balance < 0) {
            throw new WalletException('Saldo dompet tidak cukup.');
        }

        $transaction = WalletTransaction::create([
            ...$attributes,
            'wallet_id' => $locked->id,
            'type' => $type,
            'direction' => $direction,
            'amount' => $amount,
            'balance_after' => $balance,
        ]);

        // Lewat query agar saldo tidak ikut jejak audit; jejaknya sudah ada di buku besar.
        Wallet::query()->whereKey($locked->id)->update(['balance' => $balance]);
        $locked->setRawAttributes(['balance' => $balance] + $locked->getAttributes(), true);

        return $transaction;
    }
}
