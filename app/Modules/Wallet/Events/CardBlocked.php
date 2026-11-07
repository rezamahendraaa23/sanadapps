<?php

namespace App\Modules\Wallet\Events;

use App\Modules\Wallet\Models\StudentCard;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

class CardBlocked implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(public StudentCard $card) {}
}
