<?php

namespace App\Modules\Wallet\Actions;

use App\Modules\Core\Actions\RecordAudit;
use App\Modules\Finance\Models\CashCategory;
use App\Modules\Finance\Models\CashTransaction;
use App\Modules\Wallet\Models\Wallet;
use App\Modules\Wallet\Support\WalletException;
use Illuminate\Support\Facades\DB;

class WithdrawBalance
{
    public function __construct(private WalletLedger $ledger, private RecordAudit $audit) {}

    /**
     * Sisa saldo santri yang lulus atau keluar diserahkan tunai; penerimanya tercatat (WAL-05).
     *
     * @return int Nominal yang diserahkan.
     */
    public function handle(Wallet $wallet, string $receiver, int $userId): int
    {
        if (trim($receiver) === '') {
            throw new WalletException('Nama penerima wajib diisi.');
        }

        if ($wallet->student->status === 'active') {
            throw new WalletException('Saldo hanya bisa ditarik setelah santri lulus atau keluar.');
        }

        return DB::transaction(function () use ($wallet, $receiver, $userId): int {
            $locked = $this->ledger->lock($wallet->id);
            $amount = $locked->balance;

            if ($amount === 0) {
                throw new WalletException('Saldo sudah kosong.');
            }

            $transaction = $this->ledger->post($locked, 'refund', 'debit', $amount, ['note' => 'Diserahkan kepada '.trim($receiver), 'created_by' => $userId]);

            CashTransaction::create([
                'type' => 'out',
                'category_id' => CashCategory::named(CashCategory::WALLET_WITHDRAW, 'out')->id,
                'amount' => $amount,
                'transaction_date' => now(config('sanad.display_timezone'))->toDateString(),
                'description' => 'Penarikan saldo '.$wallet->student->name.', diserahkan kepada '.trim($receiver),
                'source_type' => 'wallet_transaction',
                'source_id' => $transaction->id,
                'created_by' => $userId,
            ]);

            $this->audit->handle('wallet_withdraw', 'Wallet', $wallet->id, null, ['amount' => $amount, 'receiver' => trim($receiver)]);

            return $amount;
        });
    }
}
