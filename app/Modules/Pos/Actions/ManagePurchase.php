<?php

namespace App\Modules\Pos\Actions;

use App\Modules\Core\Models\User;
use App\Modules\Finance\Support\DocumentNumber;
use App\Modules\Finance\Support\Rupiah;
use App\Modules\Pos\Models\Merchant;
use App\Modules\Pos\Models\MerchantProduct;
use App\Modules\Pos\Models\Purchase;
use App\Modules\Pos\Models\PurchaseItem;
use App\Modules\Pos\Models\Supplier;
use App\Modules\Pos\Models\SupplierPayment;
use App\Modules\Wallet\Support\WalletException;
use Illuminate\Support\Facades\DB;

/**
 * Pembelian dari pemasok: stok bertambah, harga pokok ikut harga beli terakhir, utang tercatat
 * sampai dibayar lunas.
 */
class ManagePurchase
{
    public function __construct(private StockLedger $stock) {}

    /**
     * @param  list<array{product_id: int, qty: int, unit_cost: int, expires_on?: string|null}>  $lines
     */
    public function create(Merchant $merchant, ?int $supplierId, string $date, array $lines, int $paidNow, ?string $dueOn, ?string $note, User $user): Purchase
    {
        if ($lines === []) {
            throw new WalletException('Tambahkan minimal satu barang.');
        }

        if ($supplierId !== null && ! Supplier::query()->whereKey($supplierId)->where('merchant_id', $merchant->id)->exists()) {
            throw new WalletException('Pemasok tidak ditemukan.');
        }

        return DB::transaction(function () use ($merchant, $supplierId, $date, $lines, $paidNow, $dueOn, $note, $user): Purchase {
            $products = MerchantProduct::query()->where('merchant_id', $merchant->id)->whereIn('id', array_column($lines, 'product_id'))->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $total = 0;

            foreach ($lines as $line) {
                if (! isset($products[$line['product_id']]) || $line['qty'] < 1 || $line['unit_cost'] < 0) {
                    throw new WalletException('Ada baris barang yang tidak valid.');
                }
                $total += $line['qty'] * $line['unit_cost'];
            }

            if ($paidNow < 0 || $paidNow > $total) {
                throw new WalletException('Dibayar sekarang harus antara 0 dan '.Rupiah::format($total).'.');
            }

            $purchase = Purchase::create([
                'merchant_id' => $merchant->id,
                'supplier_id' => $supplierId,
                'purchase_no' => DocumentNumber::next('PB'),
                'purchased_on' => $date,
                'total' => $total,
                'paid_amount' => 0,
                'due_on' => $dueOn ?: null,
                'note' => filled($note) ? trim((string) $note) : null,
                'created_by' => $user->id,
            ]);

            foreach ($lines as $line) {
                $product = $products[$line['product_id']];
                PurchaseItem::create([
                    'purchase_id' => $purchase->id,
                    'product_id' => $product->id,
                    'qty' => $line['qty'],
                    'unit_cost' => $line['unit_cost'],
                    'amount' => $line['qty'] * $line['unit_cost'],
                    'expires_on' => ($line['expires_on'] ?? null) ?: null,
                ]);

                // Produk tanpa pelacakan stok mulai dilacak sejak pembelian pertama.
                if ($product->stock === null) {
                    MerchantProduct::query()->whereKey($product->id)->update(['stock' => 0]);
                    $product->setRawAttributes(['stock' => 0] + $product->getAttributes(), true);
                }
                $this->stock->move($product, 'purchase', $line['qty'], 'Pembelian '.$purchase->purchase_no, $user->id, 'purchase', $purchase->id);
                MerchantProduct::query()->whereKey($product->id)->update(['cost_price' => $line['unit_cost']]);
            }

            if ($paidNow > 0) {
                $this->recordPayment($purchase, $paidNow, 'cash', $date, null, $user);
            }

            return $purchase->fresh();
        });
    }

    public function pay(Purchase $purchase, int $amount, string $method, string $date, ?string $note, User $user): void
    {
        DB::transaction(function () use ($purchase, $amount, $method, $date, $note, $user): void {
            $locked = Purchase::query()->whereKey($purchase->id)->lockForUpdate()->firstOrFail();
            $this->recordPayment($locked, $amount, $method, $date, $note, $user);
        });
        $purchase->refresh();
    }

    private function recordPayment(Purchase $locked, int $amount, string $method, string $date, ?string $note, User $user): void
    {
        if ($amount < 1 || $amount > $locked->outstanding()) {
            throw new WalletException('Nominal harus antara Rp 1 dan sisa utang '.Rupiah::format($locked->outstanding()).'.');
        }

        SupplierPayment::create([
            'purchase_id' => $locked->id,
            'amount' => $amount,
            'paid_on' => $date,
            'method' => in_array($method, ['cash', 'transfer'], true) ? $method : 'cash',
            'note' => filled($note) ? trim((string) $note) : null,
            'created_by' => $user->id,
        ]);
        $locked->update(['paid_amount' => $locked->paid_amount + $amount]);
    }
}
