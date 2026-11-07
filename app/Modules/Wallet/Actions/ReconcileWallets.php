<?php

namespace App\Modules\Wallet\Actions;

use App\Modules\Core\Models\InAppNotification;
use App\Modules\Core\Models\User;
use App\Modules\Pos\Models\MerchantLedgerEntry;
use App\Modules\Wallet\Models\Wallet;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ReconcileWallets
{
    /**
     * Job harian: saldo dompet harus sama dengan jumlah buku besarnya (WAL-03).
     *
     * @return list<array{wallet: string, balance: int, ledger: int}>
     */
    public function handle(): array
    {
        $ledger = DB::table('wallet_transactions')
            ->selectRaw("wallet_id, SUM(CASE WHEN direction = 'credit' THEN amount ELSE -amount END) AS total")
            ->groupBy('wallet_id');

        $differences = Wallet::query()
            ->leftJoinSub($ledger, 'ledger', 'ledger.wallet_id', '=', 'wallets.id')
            ->whereRaw('wallets.balance <> COALESCE(ledger.total, 0)')
            ->get(['wallets.uuid', 'wallets.balance', DB::raw('COALESCE(ledger.total, 0) AS ledger_total')])
            ->map(fn ($row) => ['wallet' => $row->uuid, 'balance' => (int) $row->balance, 'ledger' => (int) $row->ledger_total])
            ->all();

        $merchants = array_values(array_unique([...MerchantLedgerEntry::mismatchedMerchants(), ...MerchantLedgerEntry::salesMismatchedMerchants()]));

        if ($differences !== [] || $merchants !== []) {
            Log::critical('Selisih saldo dompet atau merchant', ['wallets' => $differences, 'merchants' => $merchants]);

            foreach (User::permission('settings.manage')->where('is_active', true)->get() as $admin) {
                InAppNotification::create([
                    'user_id' => $admin->id,
                    'title' => 'Selisih saldo terdeteksi',
                    'body' => count($differences).' dompet dan '.count($merchants).' merchant tidak cocok dengan buku besarnya. Hubungi teknisi.',
                    'dedupe_key' => 'reconcile:'.now(config('sanad.display_timezone'))->toDateString().':'.$admin->id,
                ]);
            }
        }

        return $differences;
    }
}
