<?php

namespace App\Modules\Wallet\Support;

use App\Modules\Pos\Models\PosOrder;

/**
 * Hasil satu percobaan belanja, untuk layar kasir dan API perangkat.
 */
final class ChargeResult
{
    private function __construct(
        public readonly bool $ok,
        public readonly string $code,
        public readonly string $message,
        public readonly ?PosOrder $order = null,
        public readonly ?int $balance = null,
    ) {}

    public static function paid(PosOrder $order, int $balance): self
    {
        return new self(true, 'paid', 'Berhasil', $order, $balance);
    }

    public static function denied(string $code, string $message): self
    {
        return new self(false, $code, $message);
    }

    public function needsPin(): bool
    {
        return in_array($this->code, ['pin_required', 'pin_wrong'], true);
    }
}
