<?php

namespace App\Modules\Wallet\Actions;

use App\Modules\Core\Actions\RecordAudit;
use App\Modules\Wallet\Models\Wallet;
use App\Modules\Wallet\Models\WalletTransaction;
use App\Modules\Wallet\Support\WalletException;
use Illuminate\Support\Facades\DB;

class AdjustWallet
{
    public function __construct(private WalletLedger $ledger, private RecordAudit $audit) {}

    public function handle(Wallet $wallet, string $direction, int $amount, string $reason, int $userId): WalletTransaction
    {
        if (trim($reason) === '') {
            throw new WalletException('Alasan penyesuaian wajib diisi.');
        }

        return DB::transaction(function () use ($wallet, $direction, $amount, $reason, $userId): WalletTransaction {
            $transaction = $this->ledger->post($this->ledger->lock($wallet->id), 'adjustment', $direction, $amount, ['note' => trim($reason), 'created_by' => $userId]);
            $this->audit->handle('wallet_adjust', 'Wallet', $wallet->id, null, ['direction' => $direction, 'amount' => $amount, 'reason' => trim($reason)]);

            return $transaction;
        });
    }
}
