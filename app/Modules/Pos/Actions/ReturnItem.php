<?php

namespace App\Modules\Pos\Actions;

use App\Modules\Core\Actions\RecordAudit;
use App\Modules\Core\Models\User;
use App\Modules\Pos\Models\Merchant;
use App\Modules\Pos\Models\MerchantLedgerEntry;
use App\Modules\Pos\Models\MerchantProduct;
use App\Modules\Pos\Models\PosOrder;
use App\Modules\Pos\Models\PosOrderItem;
use App\Modules\Pos\Models\PosReturn;
use App\Modules\Wallet\Actions\WalletLedger;
use App\Modules\Wallet\Support\WalletException;
use Illuminate\Support\Facades\DB;

/**
 * Retur sebagian: uang kembali sebesar harga bersih barang yang diretur. Barang layak jual masuk
 * stok lagi, barang rusak tidak. Kartu dikembalikan ke dompet, tunai dikembalikan kasir.
 */
class ReturnItem
{
    public function __construct(private WalletLedger $ledger, private StockLedger $stock, private RecordAudit $audit) {}

    public function handle(PosOrder $order, PosOrderItem $item, int $qty, bool $restock, string $reason, User $user): PosReturn
    {
        if (trim($reason) === '') {
            throw new WalletException('Alasan retur wajib diisi.');
        }

        if ($qty < 1) {
            throw new WalletException('Jumlah retur minimal satu.');
        }

        if (! $user->can('pos.void') || ! Merchant::query()->accessibleBy($user)->whereKey($order->merchant_id)->exists()) {
            throw new WalletException('Retur hanya oleh pengelola merchant atau admin.');
        }

        return DB::transaction(function () use ($order, $item, $qty, $restock, $reason, $user): PosReturn {
            $wallet = $order->isCash() ? null : $this->ledger->lock($order->walletTransaction()->value('wallet_id'));
            $merchant = Merchant::query()->whereKey($order->merchant_id)->lockForUpdate()->firstOrFail();
            $lockedOrder = PosOrder::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            $lockedItem = PosOrderItem::query()->whereKey($item->id)->where('pos_order_id', $lockedOrder->id)->lockForUpdate()->firstOrFail();

            if ($lockedOrder->status !== 'paid') {
                throw new WalletException('Transaksi yang dibatalkan tidak bisa diretur.');
            }

            $left = $lockedItem->qty - $lockedItem->returned_qty;
            if ($qty > $left) {
                throw new WalletException("Sisa {$lockedItem->name} yang bisa diretur: {$left}.");
            }

            // Barang terakhir menutup sisa pembulatan, jadi jumlah semua retur sama dengan harga bersih baris.
            $alreadyReturned = (int) PosReturn::query()->where('pos_order_item_id', $lockedItem->id)->sum('amount');
            $amount = $qty === $left ? $lockedItem->amount - $alreadyReturned : intdiv($lockedItem->amount * $qty, $lockedItem->qty);

            $return = PosReturn::create([
                'pos_order_id' => $lockedOrder->id,
                'pos_order_item_id' => $lockedItem->id,
                'qty' => $qty,
                'amount' => $amount,
                'restock' => $restock,
                'reason' => trim($reason),
                'created_by' => $user->id,
            ]);

            if ($wallet !== null && $amount > 0) {
                // Kumulatif agar retur penuh mengembalikan komisi tepat seluruhnya, tanpa sisa pembulatan.
                $commissionBack = intdiv($lockedOrder->commission * ($lockedOrder->returned_amount + $amount), $lockedOrder->total)
                    - intdiv($lockedOrder->commission * $lockedOrder->returned_amount, $lockedOrder->total);

                if ($merchant->balance() < $amount - $commissionBack) {
                    throw new WalletException('Saldo merchant sudah disalurkan. Selesaikan lewat penyesuaian oleh bendahara.');
                }

                $transaction = $this->ledger->post($wallet, 'refund', 'credit', $amount, [
                    'ref_type' => 'pos_return',
                    'ref_id' => $return->id,
                    'merchant_id' => $merchant->id,
                    'idempotency_key' => 'return:'.$return->id,
                    'note' => 'Retur '.$lockedItem->name.': '.trim($reason),
                    'created_by' => $user->id,
                ]);
                $return->update(['wallet_transaction_id' => $transaction->id]);

                if ($commissionBack > 0) {
                    MerchantLedgerEntry::post($merchant, 'commission', $commissionBack, 'pos_return', $return->id, 'Retur');
                }
                MerchantLedgerEntry::post($merchant, 'refund', -$amount, 'pos_return', $return->id, trim($reason));
            }

            if ($restock && $lockedItem->product_id !== null) {
                $product = MerchantProduct::query()->whereKey($lockedItem->product_id)->lockForUpdate()->first();
                if ($product?->stock !== null) {
                    $this->stock->move($product, 'return', $qty, trim($reason), $user->id, 'pos_return', $return->id);
                }
            }

            $lockedItem->update(['returned_qty' => $lockedItem->returned_qty + $qty]);
            $lockedOrder->update(['returned_amount' => $lockedOrder->returned_amount + $amount]);
            $this->audit->handle('pos_return', 'PosOrder', $lockedOrder->id, null, ['receipt_no' => $lockedOrder->receipt_no, 'item' => $lockedItem->name, 'qty' => $qty, 'amount' => $amount, 'restock' => $restock]);

            return $return;
        });
    }
}
