<?php

namespace App\Modules\Payment\Actions;

use App\Modules\Core\Models\WebhookEvent;
use App\Modules\Finance\Models\Payment;
use App\Modules\Payment\Support\MidtransConfig;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class ProcessMidtransWebhook
{
    public function __construct(private MidtransConfig $config, private ApplyGatewayStatus $status) {}

    /**
     * @param  array<string, mixed>  $payload
     * @return int Kode HTTP jawaban.
     */
    public function handle(array $payload, string $rawBody): int
    {
        $orderId = (string) ($payload['order_id'] ?? '');
        $statusCode = (string) ($payload['status_code'] ?? '');
        $gross = (string) ($payload['gross_amount'] ?? '');
        $serverKey = (string) $this->config->serverKey();

        $valid = $serverKey !== ''
            && hash_equals(hash('sha512', $orderId.$statusCode.$gross.$serverKey), (string) ($payload['signature_key'] ?? ''));

        if (! $valid) {
            $this->store('invalid:'.sha1($rawBody), $payload, false);

            return 403;
        }

        $eventKey = $orderId.'|'.($payload['transaction_status'] ?? '').'|'.$statusCode;
        $this->store($eventKey, $payload, true);

        try {
            DB::transaction(function () use ($eventKey, $payload, $orderId): void {
                // Kunci baris: dua notifikasi kembar bergantian, yang kedua melihat processed_at sudah terisi.
                $event = WebhookEvent::query()->where('provider', 'midtrans')->where('event_key', $eventKey)->lockForUpdate()->firstOrFail();

                if ($event->processed_at !== null) {
                    return;
                }

                $payment = Payment::query()->where('gateway_order_id', $orderId)->first();

                if ($payment === null) {
                    $event->update(['processed_at' => now(), 'error' => 'Order tidak dikenal.']);

                    return;
                }

                $this->status->handle($payment, $payload);
                $event->update(['processed_at' => now(), 'error' => null]);
            });
        } catch (Throwable $exception) {
            report($exception);
            WebhookEvent::query()->where('provider', 'midtrans')->where('event_key', $eventKey)->update(['error' => Str::limit($exception->getMessage(), 500)]);

            // Midtrans mengulang notifikasi bila jawabannya bukan 2xx.
            return 500;
        }

        return 200;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function store(string $eventKey, array $payload, bool $valid): void
    {
        DB::table('webhook_events')->insertOrIgnore([
            'uuid' => (string) Str::uuid7(),
            'provider' => 'midtrans',
            'event_key' => $eventKey,
            'payload' => json_encode($payload),
            'signature_valid' => $valid,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
