<?php

namespace App\Modules\Pos\Events;

use App\Modules\Pos\Models\MerchantPayout;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

class PayoutUpdated implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(public MerchantPayout $payout) {}
}
