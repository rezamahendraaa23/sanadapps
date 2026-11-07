<?php

namespace App\Modules\Finance\Events;

use App\Modules\Finance\Models\Payment;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Pembayaran online gagal atau kedaluwarsa; wali perlu diberi tahu dan boleh membayar ulang.
 */
class PaymentClosed implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(public Payment $payment) {}
}
