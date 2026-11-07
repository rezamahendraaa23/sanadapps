<?php

namespace App\Modules\Finance\Policies;

use App\Modules\Core\Models\User;
use App\Modules\Finance\Models\Invoice;

class InvoicePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('finance.view');
    }

    public function view(User $user, Invoice $invoice): bool
    {
        return Invoice::query()->visibleTo($user)->whereKey($invoice->getKey())->exists();
    }

    public function create(User $user): bool
    {
        return $user->can('finance.manage');
    }

    public function void(User $user, Invoice $invoice): bool
    {
        return $user->can('finance.manage');
    }
}
