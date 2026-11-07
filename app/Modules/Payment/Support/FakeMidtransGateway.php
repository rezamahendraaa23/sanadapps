<?php

namespace App\Modules\Payment\Support;

use App\Modules\Finance\Models\Payment;
use App\Modules\Payment\Contracts\PaymentGateway;

/**
 * Pengganti Midtrans untuk uji lokal tanpa kunci sandbox, aktif hanya lewat SANAD_FAKE_MIDTRANS di luar produksi.
 */
class FakeMidtransGateway implements PaymentGateway
{
    public function createTransaction(array $payload): array
    {
        return ['token' => 'token-uji-'.$payload['transaction_details']['order_id'], 'redirect_url' => url('/_pratinjau')];
    }

    public function status(string $orderId): ?array
    {
        $payment = Payment::query()->where('gateway_order_id', $orderId)->first();

        return $payment === null ? null : ['transaction_status' => 'pending', 'status_code' => '201', 'order_id' => $orderId, 'gross_amount' => $payment->gross_amount.'.00'];
    }

    public function cancel(string $orderId): void {}

    public function credentialsAreValid(string $serverKey, string $mode): bool
    {
        return true;
    }
}
