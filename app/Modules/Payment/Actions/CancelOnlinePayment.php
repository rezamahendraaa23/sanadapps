<?php

namespace App\Modules\Payment\Actions;

use App\Modules\Finance\Actions\FinanceException;
use App\Modules\Finance\Models\Payment;
use App\Modules\Payment\Contracts\PaymentGateway;
use App\Modules\Payment\Support\GatewayException;
use Illuminate\Support\Facades\DB;

class CancelOnlinePayment
{
    public function __construct(private PaymentGateway $gateway) {}

    /**
     * Wali mengganti cara bayar. Bila uang ternyata tetap masuk, webhook menandainya untuk ditinjau.
     */
    public function handle(Payment $payment): void
    {
        DB::transaction(function () use ($payment): void {
            $locked = Payment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();

            if ($locked->method !== 'midtrans' || $locked->status !== 'pending') {
                throw new FinanceException('Pembayaran ini tidak bisa dibatalkan lagi.');
            }

            $locked->update(['status' => 'failed', 'reject_reason' => 'Dibatalkan oleh wali.']);
        });

        try {
            $this->gateway->cancel((string) $payment->gateway_order_id);
        } catch (GatewayException $exception) {
            report($exception);
        }

        $payment->refresh();
    }
}
