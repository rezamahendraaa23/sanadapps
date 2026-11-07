<?php

namespace App\Modules\Wallet\Actions;

use App\Modules\Wallet\Models\CardScan;
use App\Modules\Wallet\Models\StudentCard;

class RecordScan
{
    public function handle(?StudentCard $card, ?int $deviceId, string $scope, string $result, ?string $reason = null, ?string $refType = null, ?int $refId = null): CardScan
    {
        return CardScan::create([
            'card_id' => $card?->id,
            'device_id' => $deviceId,
            'scope' => $scope,
            'result' => $result,
            'reason' => $reason,
            'scanned_at' => now(),
            'ref_type' => $refType,
            'ref_id' => $refId,
        ]);
    }
}
