<?php

namespace App\Modules\Payment\Actions;

use App\Modules\Finance\Models\Payment;
use App\Modules\Payment\Contracts\PaymentGateway;

class SyncPayment
{
    public function __construct(private PaymentGateway $gateway, private ApplyGatewayStatus $status) {}

    /**
     * @return string|null Status di Midtrans, null bila transaksi tidak dikenal di sana.
     */
    public function handle(Payment $payment): ?string
    {
        $data = $this->gateway->status((string) $payment->gateway_order_id);
        $payment->update(['last_synced_at' => now()]);

        if ($data === null) {
            return null;
        }

        $this->status->handle($payment, $data);

        return $data['transaction_status'] ?? null;
    }

    /**
     * Job berkala: pembayaran online yang menggantung lebih dari sejam (PRD Sistem 8.5).
     */
    public function pending(): int
    {
        $count = 0;

        Payment::query()
            ->where('method', 'midtrans')
            ->where('status', 'pending')
            ->where('created_at', '<', now()->subHour())
            ->whereNotNull('gateway_order_id')
            ->each(function (Payment $payment) use (&$count): void {
                try {
                    $this->handle($payment);
                    $count++;
                } catch (\Throwable $exception) {
                    report($exception);
                }
            });

        return $count;
    }
}
