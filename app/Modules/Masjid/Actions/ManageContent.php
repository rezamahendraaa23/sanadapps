<?php

namespace App\Modules\Masjid\Actions;

use App\Modules\Core\Actions\RecordAudit;
use App\Modules\Core\Models\Announcement;
use App\Modules\Core\Models\User;
use App\Modules\Masjid\Models\DisplayContent;
use App\Modules\Masjid\Support\MasjidException;
use Illuminate\Support\Facades\DB;

class ManageContent
{
    public function __construct(private RecordAudit $audit) {}

    public function submit(DisplayContent $content): void
    {
        if (! in_array($content->status, ['draft', 'rejected'], true)) {
            throw new MasjidException('Konten ini sudah diajukan.');
        }

        if ($content->type === 'quote' && blank($content->body['quote_id'] ?? null)) {
            throw new MasjidException('Pilih ayat atau hadis dari pustaka.');
        }

        $content->update(['status' => 'pending', 'rejected_reason' => null]);
    }

    public function approve(DisplayContent $content, User $user): void
    {
        if ($content->status !== 'pending') {
            throw new MasjidException('Hanya konten yang menunggu yang bisa disetujui.');
        }

        if (in_array($content->type, DisplayContent::SENSITIVE, true) && $content->created_by === $user->id) {
            throw new MasjidException('Konten duka cita harus disetujui orang lain.');
        }

        $content->update(['status' => 'approved', 'approved_by' => $user->id, 'approved_at' => now()]);
        $this->audit->handle('masjid.content_approved', 'display_content', $content->id);
    }

    public function reject(DisplayContent $content, string $reason, User $user): void
    {
        if (blank($reason)) {
            throw new MasjidException('Isi alasan penolakan.');
        }

        $content->update(['status' => 'rejected', 'rejected_reason' => trim($reason)]);
        $this->audit->handle('masjid.content_rejected', 'display_content', $content->id, null, ['by' => $user->id]);
    }

    /**
     * Menimpa semua konten di layar sasaran; waktu akhir wajib (PRD Masjid 8.4).
     *
     * @param  list<int>  $screenIds  Kosong berarti semua layar.
     */
    public function sendEmergency(string $message, \DateTimeInterface $until, array $screenIds, User $user): DisplayContent
    {
        if (blank($message)) {
            throw new MasjidException('Isi pesan darurat.');
        }

        if ($until <= now()) {
            throw new MasjidException('Waktu akhir harus di masa depan.');
        }

        return DB::transaction(function () use ($message, $until, $screenIds, $user): DisplayContent {
            $content = DisplayContent::create(['type' => 'emergency', 'title' => 'Pengumuman darurat', 'body' => ['text' => trim($message)], 'starts_at' => now(), 'ends_at' => $until, 'priority' => 100, 'duration_seconds' => 60, 'status' => 'approved', 'created_by' => $user->id, 'approved_by' => $user->id, 'approved_at' => now()]);
            $content->screens()->sync($screenIds);
            $this->audit->handle('masjid.emergency_sent', 'display_content', $content->id, null, ['screens' => $screenIds]);

            return $content;
        });
    }

    public function clearEmergency(User $user): int
    {
        $count = 0;

        foreach (DisplayContent::query()->where('type', 'emergency')->where('status', 'approved')->get() as $content) {
            $content->update(['status' => 'archived']);
            $this->audit->handle('masjid.emergency_cleared', 'display_content', $content->id, null, ['by' => $user->id]);
            $count++;
        }

        return $count;
    }

    /**
     * Menautkan pengumuman SANAD ke layar: tanpa salin tempel, ikut berubah dan ikut terhapus (MSJ-CMS-05).
     */
    public function syncAnnouncement(Announcement $announcement, bool $show, User $user): void
    {
        $content = DisplayContent::withTrashed()->where('announcement_id', $announcement->id)->first();

        if (! $show) {
            $content?->update(['status' => 'archived']);

            return;
        }

        $values = ['title' => $announcement->title, 'body' => ['text' => $announcement->body], 'starts_at' => $announcement->publish_at];

        if ($content !== null) {
            $content->update([...$values, 'status' => $content->status === 'archived' ? ($user->can('masjid.approve') ? 'approved' : 'pending') : $content->status]);

            return;
        }

        DisplayContent::create([...$values, 'type' => 'text', 'announcement_id' => $announcement->id, 'status' => $user->can('masjid.approve') ? 'approved' : 'pending', 'created_by' => $user->id, 'approved_by' => $user->can('masjid.approve') ? $user->id : null, 'approved_at' => $user->can('masjid.approve') ? now() : null]);
    }
}
