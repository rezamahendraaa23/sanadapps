<?php

namespace App\Modules\Notification\Jobs;

use App\Modules\Core\Models\Integration;
use App\Modules\Notification\Models\NotificationLog;
use App\Modules\Notification\Support\FonnteClient;
use App\Modules\Notification\Support\FonnteException;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\RateLimiter;
use Throwable;

class SendWhatsAppMessage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public const MAX_ATTEMPTS = 3;

    public int $tries = 0;

    public int $maxExceptions = 3;

    public int $timeout = 60;

    public function __construct(public NotificationLog $log) {}

    /**
     * Gagal diulang sampai 3 kali lalu ditandai failed (NTF-04). Antrean tertahan karena batas
     * per jam tidak dihitung sebagai percobaan.
     */
    public function handle(FonnteClient $client): void
    {
        $log = $this->log->refresh();

        if ($log->status !== 'queued') {
            return;
        }

        $limit = (int) (Integration::query()->where('provider', 'fonnte')->first()?->settings['hourly_limit'] ?? 60);

        if (RateLimiter::tooManyAttempts('fonnte-hourly', max(1, $limit))) {
            $this->release(RateLimiter::availableIn('fonnte-hourly') + random_int(5, 30));

            return;
        }

        RateLimiter::hit('fonnte-hourly', 3600);
        $log->increment('attempts');

        try {
            $id = $client->send($log->recipient, (string) ($log->payload['message'] ?? ''));
            $log->update(['status' => 'sent', 'provider_message_id' => $id, 'sent_at' => now(), 'error' => null]);
        } catch (FonnteException $exception) {
            if ($log->attempts >= self::MAX_ATTEMPTS) {
                $log->update(['status' => 'failed', 'error' => $exception->getMessage()]);

                return;
            }

            $log->update(['error' => $exception->getMessage()]);
            $this->release(60 * $log->attempts);
        }
    }

    public function failed(?Throwable $exception): void
    {
        $this->log->update(['status' => 'failed', 'error' => $exception?->getMessage() ?? 'Job gagal.']);
    }
}
