<?php

namespace App\Modules\Payment\Support;

use App\Modules\Payment\Contracts\PaymentGateway;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class MidtransGateway implements PaymentGateway
{
    public function __construct(private MidtransConfig $config) {}

    public function createTransaction(array $payload): array
    {
        try {
            $response = $this->client((string) $this->config->serverKey())
                ->post($this->snapBase($this->config->mode()).'/snap/v1/transactions', $payload);
        } catch (ConnectionException $exception) {
            throw new GatewayException('Tidak tersambung ke Midtrans.', previous: $exception);
        }

        if ($response->status() !== 201 || blank($response->json('token'))) {
            throw new GatewayException('Midtrans menolak transaksi: HTTP '.$response->status());
        }

        return ['token' => $response->json('token'), 'redirect_url' => (string) $response->json('redirect_url')];
    }

    public function status(string $orderId): ?array
    {
        try {
            $response = $this->client((string) $this->config->serverKey())
                ->get($this->apiBase($this->config->mode()).'/v2/'.rawurlencode($orderId).'/status');
        } catch (ConnectionException $exception) {
            throw new GatewayException('Tidak tersambung ke Midtrans.', previous: $exception);
        }

        if (! $response->successful()) {
            throw new GatewayException('Midtrans tidak bisa dimintai status: HTTP '.$response->status());
        }

        // Midtrans menjawab HTTP 200 dengan status_code di badan pesan, termasuk 404 untuk order tak dikenal.
        return (string) $response->json('status_code') === '404' ? null : $response->json();
    }

    public function cancel(string $orderId): void
    {
        try {
            $this->client((string) $this->config->serverKey())
                ->post($this->apiBase($this->config->mode()).'/v2/'.rawurlencode($orderId).'/cancel');
        } catch (ConnectionException $exception) {
            throw new GatewayException('Tidak tersambung ke Midtrans.', previous: $exception);
        }
    }

    public function credentialsAreValid(string $serverKey, string $mode): bool
    {
        try {
            $response = $this->client($serverKey)->get($this->apiBase($mode).'/v2/sanad-tes-koneksi/status');
        } catch (ConnectionException) {
            return false;
        }

        return $response->successful() && (string) $response->json('status_code') !== '401';
    }

    private function client(string $serverKey): PendingRequest
    {
        return Http::withBasicAuth($serverKey, '')->acceptJson()->asJson()->timeout(15);
    }

    private function snapBase(string $mode): string
    {
        return $mode === 'production' ? 'https://app.midtrans.com' : 'https://app.sandbox.midtrans.com';
    }

    private function apiBase(string $mode): string
    {
        return $mode === 'production' ? 'https://api.midtrans.com' : 'https://api.sandbox.midtrans.com';
    }
}
