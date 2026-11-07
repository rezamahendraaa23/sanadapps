<?php

namespace App\Modules\Pos\Actions;

use App\Modules\Finance\Models\CashCategory;
use App\Modules\Finance\Models\CashTransaction;
use App\Modules\Finance\Support\Rupiah;
use App\Modules\Pos\Events\PayoutUpdated;
use App\Modules\Pos\Models\Merchant;
use App\Modules\Pos\Models\MerchantLedgerEntry;
use App\Modules\Pos\Models\MerchantPayout;
use App\Modules\Wallet\Support\WalletException;
use Illuminate\Support\Facades\DB;

class ManagePayout
{
    /**
     * Saldo yang bisa diajukan: saldo dikurangi pengajuan yang masih berjalan.
     */
    public function available(Merchant $merchant): int
    {
        return $merchant->balance() - (int) MerchantPayout::query()->where('merchant_id', $merchant->id)->whereIn('status', ['requested', 'approved'])->sum('amount');
    }

    public function request(Merchant $merchant, int $amount, string $method, ?string $note, int $userId): MerchantPayout
    {
        if ($amount <= 0) {
            throw new WalletException('Isi nominal pencairan.');
        }

        return DB::transaction(function () use ($merchant, $amount, $method, $note, $userId): MerchantPayout {
            $locked = Merchant::query()->whereKey($merchant->id)->lockForUpdate()->firstOrFail();
            $available = $this->available($locked);

            if ($amount > $available) {
                throw new WalletException('Melebihi saldo yang bisa dicairkan ('.Rupiah::format($available).').');
            }

            return MerchantPayout::create([
                'merchant_id' => $locked->id,
                'amount' => $amount,
                'method' => $method === 'transfer' ? 'transfer' : 'cash',
                'note' => filled($note) ? trim((string) $note) : null,
                'requested_by' => $userId,
            ]);
        });
    }

    public function approve(MerchantPayout $payout, int $userId): void
    {
        $this->transition($payout, ['requested'], function (MerchantPayout $locked) use ($userId): void {
            $locked->update(['status' => 'approved', 'approved_by' => $userId]);
        });
    }

    public function reject(MerchantPayout $payout, string $reason, int $userId): void
    {
        if (trim($reason) === '') {
            throw new WalletException('Alasan penolakan wajib diisi.');
        }

        $this->transition($payout, ['requested', 'approved'], function (MerchantPayout $locked) use ($reason, $userId): void {
            $locked->update(['status' => 'rejected', 'reject_reason' => trim($reason), 'approved_by' => $userId]);
        });
    }

    /**
     * Saat dibayar, saldo merchant berkurang dan kas keluar tercatat bersamaan (PRD Sistem 8.7b).
     */
    public function pay(MerchantPayout $payout, int $userId): void
    {
        $this->transition($payout, ['approved'], function (MerchantPayout $locked, Merchant $merchant) use ($userId): void {
            if ($merchant->balance() < $locked->amount) {
                throw new WalletException('Saldo merchant tidak cukup untuk pencairan ini.');
            }

            MerchantLedgerEntry::post($merchant, 'payout', -$locked->amount, 'merchant_payout', $locked->id);

            $cash = CashTransaction::create([
                'type' => 'out',
                'category_id' => CashCategory::named(CashCategory::MERCHANT_PAYOUT, 'out')->id,
                'amount' => $locked->amount,
                'transaction_date' => now(config('sanad.display_timezone'))->toDateString(),
                'description' => 'Penyaluran ke '.$merchant->name,
                'source_type' => 'merchant_payout',
                'source_id' => $locked->id,
                'created_by' => $userId,
            ]);

            $locked->update(['status' => 'paid', 'paid_at' => now(), 'cash_transaction_id' => $cash->id]);
        });
    }

    /**
     * @param  list<string>  $from
     */
    private function transition(MerchantPayout $payout, array $from, callable $change): void
    {
        DB::transaction(function () use ($payout, $from, $change): void {
            $merchant = Merchant::query()->whereKey($payout->merchant_id)->lockForUpdate()->firstOrFail();
            $locked = MerchantPayout::query()->whereKey($payout->id)->lockForUpdate()->firstOrFail();

            if (! in_array($locked->status, $from, true)) {
                throw new WalletException('Status pencairan sudah berubah. Muat ulang halaman.');
            }

            $change($locked, $merchant);

            PayoutUpdated::dispatch($locked);
        });

        $payout->refresh();
    }
}
