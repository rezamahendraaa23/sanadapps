<?php

namespace App\Modules\Pos\Events;

use App\Modules\Pos\Models\MerchantProduct;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

class StockLow implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(public MerchantProduct $product, public int $stock) {}
}
