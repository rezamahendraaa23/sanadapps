<?php

namespace App\Modules\Academic\Events;

use App\Modules\Academic\Models\ReportCard;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

class ReportCardPublished implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(public ReportCard $card) {}
}
