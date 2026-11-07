<?php

namespace App\Modules\Notification\Actions;

use App\Modules\Core\Models\Announcement;
use App\Modules\Core\Models\Student;
use Illuminate\Support\Facades\Date;

class SendAnnouncement
{
    public function __construct(private QueueNotification $notifications) {}

    /**
     * Pengumuman terjadwal dikirim oleh job berkala saat waktunya tiba; kunci dedupe mencegah kirim ganda.
     */
    public function handle(Announcement $announcement): int
    {
        $students = Student::query()
            ->where('status', 'active')
            ->when($announcement->audience === 'classroom', fn ($query) => $query->where('classroom_id', $announcement->audience_id))
            ->when($announcement->audience === 'dormitory', fn ($query) => $query->where('dormitory_id', $announcement->audience_id))
            ->get();

        return $this->notifications->toGuardians(
            'announcement',
            $students,
            fn () => ['judul' => $announcement->title, 'isi' => $announcement->body],
            $announcement->title,
            route('wali.home'),
            'announcement:'.$announcement->id,
        );
    }

    public function due(): int
    {
        $count = 0;

        Announcement::query()->where('send_wa', true)->whereBetween('publish_at', [Date::now()->subDay(), Date::now()])
            ->each(function (Announcement $announcement) use (&$count): void {
                $count += $this->handle($announcement);
            });

        return $count;
    }
}
