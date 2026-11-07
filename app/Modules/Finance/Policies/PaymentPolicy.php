<?php

namespace App\Modules\Finance\Policies;

use App\Modules\Core\Models\User;
use App\Modules\Finance\Models\Payment;

class PaymentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('finance.view');
    }

    public function view(User $user, Payment $payment): bool
    {
        return Payment::query()->visibleTo($user)->whereKey($payment->getKey())->exists();
    }

    public function verify(User $user, Payment $payment): bool
    {
        return $user->can('finance.verify');
    }

    public function refund(User $user, Payment $payment): bool
    {
        return $user->can('finance.verify');
    }
}
