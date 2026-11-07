<?php

namespace App\Modules\Leave\Events;

use App\Modules\Leave\Models\LeaveRequest;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

class LeaveEvent implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    /**
     * @param  string  $kind  requested, decided, late
     */
    public function __construct(public LeaveRequest $leave, public string $kind) {}
}
