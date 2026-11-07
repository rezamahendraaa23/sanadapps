<?php

namespace App\Modules\Academic\Events;

use App\Modules\Academic\Models\AttendanceSession;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

class AttendanceClosed implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(public AttendanceSession $session) {}
}
