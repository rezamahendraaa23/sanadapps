<?php

namespace App\Modules\Pos\Actions;

use App\Modules\Pos\Events\StockLow;
use App\Modules\Pos\Models\MerchantProduct;
use App\Modules\Pos\Models\StockMovement;
use App\Modules\Wallet\Support\WalletException;
use Illuminate\Support\Facades\DB;

class StockLedger
{
    /**
     * Satu-satunya jalan stok berubah. Pemanggil memegang kunci baris produk.
     */
    public function move(MerchantProduct $locked, string $type, int $quantity, ?string $note = null, ?int $userId = null, ?string $refType = null, ?int $refId = null): StockMovement
    {
        $before = (int) $locked->stock;
        $after = $before + $quantity;

        if ($after < 0) {
            throw new WalletException("Stok {$locked->name} tidak cukup (tersisa {$before}).");
        }

        $movement = StockMovement::create([
            'product_id' => $locked->id,
            'type' => $type,
            'quantity' => $quantity,
            'stock_after' => $after,
            'note' => $note,
            'created_by' => $userId,
            'ref_type' => $refType,
            'ref_id' => $refId,
        ]);

        MerchantProduct::query()->whereKey($locked->id)->update(['stock' => $after]);
        $locked->setRawAttributes(['stock' => $after] + $locked->getAttributes(), true);

        // Hanya saat baru melewati batas, agar pengelola tidak dikabari di setiap penjualan berikutnya.
        if ($locked->min_stock !== null && $before > $locked->min_stock && $after <= $locked->min_stock) {
            StockLow::dispatch($locked, $after);
        }

        return $movement;
    }

    public function stockIn(MerchantProduct $product, int $quantity, ?string $note, int $userId): void
    {
        if ($quantity <= 0) {
            throw new WalletException('Jumlah harus lebih dari nol.');
        }

        $this->inTransaction($product, fn (MerchantProduct $locked) => $this->move($locked, 'in', $quantity, $note, $userId));
    }

    public function stockOut(MerchantProduct $product, int $quantity, string $reason, int $userId): void
    {
        if ($quantity <= 0) {
            throw new WalletException('Jumlah harus lebih dari nol.');
        }

        if (trim($reason) === '') {
            throw new WalletException('Alasan wajib diisi.');
        }

        $this->inTransaction($product, fn (MerchantProduct $locked) => $this->move($locked, 'out', -$quantity, trim($reason), $userId));
    }

    /**
     * Hitung fisik: selisih dengan sistem menjadi mutasi penyesuaian (POS-06).
     *
     * @return int Selisih; nol berarti cocok dan tidak ada mutasi.
     */
    public function opname(MerchantProduct $product, int $counted, ?string $note, int $userId): int
    {
        if ($counted < 0) {
            throw new WalletException('Hasil hitung tidak boleh minus.');
        }

        return $this->inTransaction($product, function (MerchantProduct $locked) use ($counted, $note, $userId): int {
            $difference = $counted - (int) $locked->stock;

            if ($difference !== 0) {
                $this->move($locked, 'adjust', $difference, 'Opname'.(filled($note) ? ': '.trim((string) $note) : ''), $userId);
            }

            return $difference;
        });
    }

    private function inTransaction(MerchantProduct $product, callable $callback): mixed
    {
        return DB::transaction(function () use ($product, $callback) {
            $locked = MerchantProduct::query()->whereKey($product->id)->lockForUpdate()->firstOrFail();
            $result = $callback($locked);
            $product->refresh();

            return $result;
        });
    }
}
