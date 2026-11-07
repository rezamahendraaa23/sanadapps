<?php

namespace App\Modules\Affairs\Actions;

use App\Modules\Affairs\Models\InventoryItem;
use App\Modules\Affairs\Models\InventoryMovement;
use App\Modules\Affairs\Support\AffairsException;
use Illuminate\Support\Facades\DB;

class MoveInventory
{
    /**
     * Jumlah hanya berubah lewat mutasi, dan mutasi tidak bisa dihapus (INV-01). Penyesuaian boleh negatif.
     */
    public function handle(InventoryItem $item, string $type, int $quantity, ?string $note, ?int $userId): void
    {
        if (! isset(InventoryItem::MOVEMENTS[$type])) {
            throw new AffairsException('Jenis mutasi tidak dikenal.');
        }

        if ($quantity === 0 || ($type !== 'adjust' && $quantity < 0)) {
            throw new AffairsException('Jumlah mutasi tidak boleh nol atau negatif.');
        }

        DB::transaction(function () use ($item, $type, $quantity, $note, $userId): void {
            $locked = InventoryItem::query()->whereKey($item->id)->lockForUpdate()->firstOrFail();
            $delta = match ($type) {
                'in', 'repair', 'adjust' => $quantity,
                default => -$quantity,
            };

            if ($locked->quantity + $delta < 0) {
                throw new AffairsException("Jumlah tersedia hanya {$locked->quantity}.");
            }

            $locked->update(['quantity' => $locked->quantity + $delta]);
            InventoryMovement::create(['item_id' => $locked->id, 'type' => $type, 'quantity' => $quantity, 'note' => $note, 'created_by' => $userId]);
        });
    }
}
