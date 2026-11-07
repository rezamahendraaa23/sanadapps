<?php

namespace App\Modules\Notification\Actions;

use App\Modules\Core\Models\Guardian;
use App\Modules\Core\Models\InAppNotification;
use App\Modules\Core\Models\Student;
use App\Modules\Core\Models\User;
use App\Modules\Core\Support\ModuleRegistry;
use App\Modules\Notification\Jobs\SendWhatsAppMessage;
use App\Modules\Notification\Models\GuardianNotificationPref;
use App\Modules\Notification\Models\NotificationLog;
use App\Modules\Notification\Models\NotificationTemplate;
use App\Modules\Notification\Support\NotificationCatalog;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class QueueNotification
{
    private const GAP_MIN_SECONDS = 4;

    private const GAP_MAX_SECONDS = 12;

    public function __construct(private ModuleRegistry $modules) {}

    /**
     * Mengirim ke semua wali santri yang boleh menerima, satu pesan per nomor.
     *
     * @param  iterable<Student>  $students
     * @param  callable(Guardian): array<string, string|int|null>  $variables
     * @return int Jumlah pesan WA yang masuk antrean.
     */
    public function toGuardians(string $eventKey, iterable $students, callable $variables, string $title, ?string $link = null, ?string $dedupe = null): int
    {
        $category = NotificationCatalog::EVENTS[$eventKey]['category'];
        $count = 0;

        $guardians = collect($students)
            ->flatMap(fn (Student $student) => $student->guardians()->wherePivot('can_receive_wa', true)->get())
            ->unique('id');

        foreach ($guardians as $guardian) {
            $vars = $variables($guardian);
            $message = $this->renderBody($eventKey, $vars);

            $key = $dedupe ? $dedupe.':'.$guardian->id : null;

            if ($guardian->user_id && $message !== null) {
                $this->storeInApp($guardian->user_id, $title, $message, $link, $key);
            }

            if ($message !== null && $this->guardianAccepts($guardian, $category)) {
                $count += (int) $this->queue($eventKey, $guardian->phone_wa, $message, $key);
            }
        }

        return $count;
    }

    /**
     * @param  array<string, string|int|null>  $variables
     */
    public function toStaff(string $eventKey, User $user, array $variables, string $title, ?string $link = null): void
    {
        $message = $this->renderBody($eventKey, $variables);

        if ($message === null) {
            return;
        }

        InAppNotification::create(['user_id' => $user->id, 'title' => $title, 'body' => $message, 'link' => $link]);

        if (filled($user->phone)) {
            $this->queue($eventKey, $user->phone, $message);
        }
    }

    /**
     * @param  array<string, string|int|null>  $variables
     */
    public function renderBody(string $eventKey, array $variables): ?string
    {
        $template = NotificationTemplate::query()->where('event_key', $eventKey)->where('channel', 'whatsapp')->first();

        if ($template !== null && ! $template->is_enabled) {
            return null;
        }

        return NotificationCatalog::render($template?->body ?? NotificationCatalog::EVENTS[$eventKey]['body'], $variables);
    }

    private function storeInApp(int $userId, string $title, string $message, ?string $link, ?string $dedupe): void
    {
        try {
            DB::transaction(fn () => InAppNotification::create(['user_id' => $userId, 'title' => $title, 'body' => $message, 'link' => $link, 'dedupe_key' => $dedupe]));
        } catch (UniqueConstraintViolationException) {
            // Sudah pernah dibuat oleh jalannya job sebelumnya.
        }
    }

    private function guardianAccepts(Guardian $guardian, ?string $category): bool
    {
        if ($category === null || NotificationCatalog::CATEGORIES[$category]['critical']) {
            return true;
        }

        return GuardianNotificationPref::query()->where('guardian_id', $guardian->id)->where('category', $category)->value('is_enabled') ?? true;
    }

    /**
     * @return bool false bila modul mati atau pesan yang sama sudah pernah diantrekan.
     */
    private function queue(string $eventKey, string $recipient, string $message, ?string $dedupe = null): bool
    {
        if (! $this->modules->enabled('notification_wa') || config('sanad.notifications_off')) {
            return false;
        }

        try {
            $log = DB::transaction(fn () => NotificationLog::create([
                'recipient' => $recipient,
                'event_key' => $eventKey,
                'payload' => ['message' => $message],
                'dedupe_key' => $dedupe,
            ]));
        } catch (UniqueConstraintViolationException) {
            return false;
        }

        SendWhatsAppMessage::dispatch($log)->onQueue('notifications')->delay(self::nextSlot());

        return true;
    }

    /**
     * Pesan dijadwalkan berurutan dengan jeda acak, agar pengiriman massal tidak serentak (NTF-03).
     */
    public static function nextSlot(): \DateTimeInterface
    {
        return Cache::lock('fonnte-slot', 5)->block(5, function () {
            $now = microtime(true);
            $next = max((float) Cache::get('fonnte-next-slot', 0), $now) + random_int(self::GAP_MIN_SECONDS, self::GAP_MAX_SECONDS);
            Cache::put('fonnte-next-slot', $next, 86400);

            return now()->addSeconds((int) ceil($next - $now));
        });
    }
}
