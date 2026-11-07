<?php

namespace App\Modules\Pos\Actions;

use App\Modules\Core\Models\User;
use App\Modules\Pos\Models\Merchant;
use App\Modules\Pos\Models\PosShift;
use App\Modules\Wallet\Support\WalletException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Shift kasir: modal awal saat buka, hitung uang fisik saat tutup. Selisih dicatat, tidak dikoreksi otomatis.
 */
class ManageShift
{
    public function open(Merchant $merchant, User $cashier, int $openingCash): PosShift
    {
        if ($openingCash < 0) {
            throw new WalletException('Modal awal tidak boleh minus.');
        }

        if (self::current($merchant, $cashier) !== null) {
            throw new WalletException('Shift Anda di merchant ini masih terbuka.');
        }

        try {
            // Savepoint: pelanggaran unik karena dua klik bersamaan tidak membatalkan transaksi pemanggil.
            return DB::transaction(fn () => PosShift::create([
                'merchant_id' => $merchant->id,
                'cashier_user_id' => $cashier->id,
                'opened_at' => now(),
                'opening_cash' => $openingCash,
            ]));
        } catch (UniqueConstraintViolationException) {
            throw new WalletException('Shift Anda di merchant ini masih terbuka.');
        }
    }

    public static function current(Merchant $merchant, User $cashier): ?PosShift
    {
        return PosShift::query()->where('merchant_id', $merchant->id)->where('cashier_user_id', $cashier->id)->whereNull('closed_at')->first();
    }

    /**
     * @return array{cash_sales: int, card_sales: int, refunds: int, order_count: int, expected_cash: int}
     */
    public static function totals(PosShift $shift): array
    {
        $row = DB::table('pos_orders')->where('shift_id', $shift->id)->where('status', 'paid')
            ->selectRaw("COALESCE(SUM(CASE WHEN scan_method = 'cash' THEN total - returned_amount ELSE 0 END), 0) AS cash_sales")
            ->selectRaw("COALESCE(SUM(CASE WHEN scan_method <> 'cash' THEN total - returned_amount ELSE 0 END), 0) AS card_sales")
            ->selectRaw('COALESCE(SUM(returned_amount), 0) AS refunds, COUNT(*) AS order_count')
            ->first();

        return [
            'cash_sales' => (int) $row->cash_sales,
            'card_sales' => (int) $row->card_sales,
            'refunds' => (int) $row->refunds,
            'order_count' => (int) $row->order_count,
            'expected_cash' => $shift->opening_cash + (int) $row->cash_sales,
        ];
    }

    public function close(PosShift $shift, User $user, int $countedCash, ?string $note): PosShift
    {
        if ($shift->cashier_user_id !== $user->id && ! $user->can('pos.void')) {
            throw new WalletException('Shift ini milik kasir lain.');
        }

        if ($countedCash < 0) {
            throw new WalletException('Uang yang dihitung tidak boleh minus.');
        }

        return DB::transaction(function () use ($shift, $countedCash, $note): PosShift {
            $locked = PosShift::query()->whereKey($shift->id)->lockForUpdate()->firstOrFail();

            if ($locked->closed_at !== null) {
                throw new WalletException('Shift ini sudah ditutup.');
            }

            $locked->update([...self::totals($locked), 'closed_at' => now(), 'counted_cash' => $countedCash, 'note' => filled($note) ? trim((string) $note) : null]);

            return $locked;
        });
    }
}
