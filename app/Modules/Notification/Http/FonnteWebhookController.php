<?php

namespace App\Modules\Notification\Http;

use App\Modules\Core\Models\Integration;
use App\Modules\Core\Models\WebhookEvent;
use App\Modules\Notification\Models\NotificationLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class FonnteWebhookController
{
    /**
     * Fonnte tidak menyertakan tanda tangan, jadi keaslian dijaga token rahasia di alamat webhook.
     */
    public function __invoke(Request $request, string $secret): JsonResponse
    {
        $expected = Integration::query()->where('provider', 'fonnte')->first()?->settings['webhook_secret'] ?? null;

        if (blank($expected) || ! hash_equals($expected, $secret)) {
            return response()->json(['ok' => false], 403);
        }

        $payload = $request->json()->all();
        $messageId = (string) ($payload['id'] ?? '');
        $state = Str::lower((string) ($payload['state'] ?? $payload['status'] ?? ''));

        WebhookEvent::query()->firstOrCreate(
            ['provider' => 'fonnte', 'event_key' => $messageId.'|'.$state.'|'.($payload['stateid'] ?? '')],
            ['payload' => $payload, 'signature_valid' => true, 'processed_at' => now()],
        );

        if ($messageId !== '') {
            $status = match (true) {
                in_array($state, ['delivered', 'read', 'played'], true) => 'delivered',
                in_array($state, ['failed', 'invalid', 'expired'], true) => 'failed',
                default => null,
            };

            if ($status !== null) {
                // Status terkirim tidak boleh mundur oleh kabar yang datang belakangan.
                NotificationLog::query()->where('provider_message_id', $messageId)->where('status', '!=', 'delivered')
                    ->update(['status' => $status, 'error' => $status === 'failed' ? 'Fonnte melaporkan: '.$state : null]);
            }
        }

        return response()->json(['ok' => true]);
    }
}
