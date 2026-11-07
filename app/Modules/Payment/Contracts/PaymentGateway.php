<?php

namespace App\Modules\Payment\Contracts;

use App\Modules\Payment\Support\GatewayException;

interface PaymentGateway
{
    /**
     * @param  array<string, mixed>  $payload
     * @return array{token: string, redirect_url: string}
     *
     * @throws GatewayException
     */
    public function createTransaction(array $payload): array;

    /**
     * @return array<string, mixed>|null null bila transaksi belum dikenal gateway.
     *
     * @throws GatewayException
     */
    public function status(string $orderId): ?array;

    /**
     * @throws GatewayException
     */
    public function cancel(string $orderId): void;

    public function credentialsAreValid(string $serverKey, string $mode): bool;
}
