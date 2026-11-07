<?php

namespace App\Modules\Pos\Actions;

use App\Modules\Core\Actions\RecordAudit;
use App\Modules\Core\Models\User;
use App\Modules\Pos\Models\Merchant;
use App\Modules\Pos\Models\MerchantLedgerEntry;
use App\Modules\Pos\Models\PosOrder;
use App\Modules\Wallet\Actions\WalletLedger;
use App\Modules\Wallet\Support\WalletException;
use Illuminate\Support\Facades\DB;

class VoidOrder
{
    public function __construct(private WalletLedger $ledger, private RecordAudit $audit) {}

    /**
     * Saldo kembali lewat baris baru di buku besar dompet dan merchant, tidak ada yang dihapus (POS-12).
     */
    public function handle(PosOrder $order, string $reason, User $user): void
    {
        if (trim($reason) === '') {
            throw new WalletException('Alasan pembatalan wajib diisi.');
        }

        if (! $order->canBeVoidedBy($user)) {
            throw new WalletException($order->status === 'void'
                ? 'Transaksi ini sudah dibatalkan.'
                : 'Lewat '.PosOrder::CASHIER_VOID_MINUTES.' menit, pembatalan harus oleh pengelola atau admin.');
        }

        DB::transaction(function () use ($order, $reason, $user): void {
            $walletId = $order->walletTransaction()->value('wallet_id');
            $wallet = $this->ledger->lock($walletId);
            $merchant = Merchant::query()->whereKey($order->merchant_id)->lockForUpdate()->firstOrFail();
            $locked = PosOrder::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== 'paid') {
                throw new WalletException('Transaksi ini sudah dibatalkan.');
            }

            $net = $locked->total - $locked->commission;

            if ($merchant->balance() < $net) {
                throw new WalletException('Saldo merchant sudah disalurkan. Selesaikan lewat penyesuaian oleh bendahara.');
            }

            $this->ledger->post($wallet, 'refund', 'credit', $locked->total, [
                'ref_type' => 'pos_order',
                'ref_id' => $locked->id,
                'merchant_id' => $locked->merchant_id,
                'idempotency_key' => 'void:'.$locked->id,
                'note' => trim($reason),
                'created_by' => $user->id,
            ]);

            if ($locked->commission > 0) {
                MerchantLedgerEntry::post($merchant, 'commission', $locked->commission, 'pos_order', $locked->id, 'Pembatalan');
            }

            MerchantLedgerEntry::post($merchant, 'refund', -$locked->total, 'pos_order', $locked->id, trim($reason));

            $locked->update(['status' => 'void', 'voided_by' => $user->id, 'void_reason' => trim($reason), 'voided_at' => now()]);

            $this->audit->handle('pos_void', 'PosOrder', $locked->id, null, ['receipt_no' => $locked->receipt_no, 'reason' => trim($reason)]);
        });

        $order->refresh();
    }
}
