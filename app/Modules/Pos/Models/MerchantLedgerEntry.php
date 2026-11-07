<?php

namespace App\Modules\Pos\Models;

use App\Support\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class MerchantLedgerEntry extends Model
{
    use HasPublicUuid;

    public const UPDATED_AT = null;

    protected $table = 'merchant_ledger';

    protected $guarded = ['id', 'uuid'];

    protected function casts(): array
    {
        return ['amount' => 'integer', 'balance_after' => 'integer', 'created_at' => 'immutable_datetime'];
    }

    /**
     * Baris dengan kunci merchant dipegang pemanggil, jadi saldo terakhir pasti saldo yang benar.
     */
    public static function post(Merchant $locked, string $type, int $amount, ?string $refType = null, ?int $refId = null, ?string $note = null): self
    {
        return static::create([
            'merchant_id' => $locked->id,
            'type' => $type,
            'amount' => $amount,
            'balance_after' => $locked->balance() + $amount,
            'ref_type' => $refType,
            'ref_id' => $refId,
            'note' => $note,
        ]);
    }

    /**
     * @return list<int> Merchant yang penjualan bersihnya di buku besar tidak sama dengan belanja dompet (PRD Sistem 8.7b).
     */
    public static function salesMismatchedMerchants(): array
    {
        $wallet = DB::table('wallet_transactions')
            ->whereNotNull('merchant_id')
            ->where(fn ($query) => $query->where('type', 'spend')->orWhere(fn ($refund) => $refund->where('type', 'refund')->where('direction', 'credit')))
            ->selectRaw("merchant_id, SUM(CASE WHEN type = 'spend' THEN amount ELSE -amount END) AS total")
            ->groupBy('merchant_id');

        $ledger = DB::table('merchant_ledger')->whereIn('type', ['sale', 'refund'])->selectRaw('merchant_id, SUM(amount) AS total')->groupBy('merchant_id');

        return DB::query()->fromSub($ledger, 'ledger')
            ->leftJoinSub($wallet, 'wallet', 'wallet.merchant_id', '=', 'ledger.merchant_id')
            ->whereRaw('ledger.total <> COALESCE(wallet.total, 0)')
            ->pluck('ledger.merchant_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * @return list<int> Merchant yang saldo terakhirnya tidak sama dengan jumlah buku besarnya.
     */
    public static function mismatchedMerchants(): array
    {
        return DB::table('merchant_ledger as entries')
            ->selectRaw('entries.merchant_id')
            ->groupBy('entries.merchant_id')
            ->havingRaw('SUM(entries.amount) <> (SELECT latest.balance_after FROM merchant_ledger latest WHERE latest.merchant_id = entries.merchant_id ORDER BY latest.id DESC LIMIT 1)')
            ->pluck('merchant_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }
}
