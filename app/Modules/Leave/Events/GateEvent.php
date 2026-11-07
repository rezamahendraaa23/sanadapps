<?php

namespace App\Modules\Leave\Events;

use App\Modules\Leave\Models\GateLog;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

class GateEvent implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(public GateLog $log) {}
}
