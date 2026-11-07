<?php

namespace App\Modules\Wallet\Events;

use App\Modules\Pos\Models\PosOrder;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

class WalletSpent implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(public PosOrder $order, public int $balance) {}
}
