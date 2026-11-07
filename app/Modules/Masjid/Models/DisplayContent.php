<?php

namespace App\Modules\Masjid\Models;

use App\Modules\Core\Models\Announcement;
use App\Modules\Core\Models\Student;
use App\Modules\Core\Support\Audited;
use App\Support\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class DisplayContent extends Model
{
    use Audited, HasPublicUuid, SoftDeletes;

    public const TYPES = ['text' => 'Pengumuman', 'poster' => 'Poster', 'video' => 'Video pendek', 'agenda' => 'Agenda', 'qr_donation' => 'QR infaq', 'quote' => 'Ayat atau hadis', 'obituary' => 'Duka cita'];

    public const STATUSES = ['draft' => 'Draf', 'pending' => 'Menunggu', 'approved' => 'Disetujui', 'rejected' => 'Ditolak', 'archived' => 'Diarsipkan'];

    /** Pembuat dan penyetuju harus berbeda (PRD Masjid 8.3). */
    public const SENSITIVE = ['obituary'];

    protected $guarded = ['id', 'uuid'];

    protected $attributes = ['status' => 'draft', 'body' => '{}', 'duration_seconds' => 12, 'priority' => 0];

    protected function casts(): array
    {
        return ['body' => 'array', 'schedule_rule' => 'array', 'starts_at' => 'immutable_datetime', 'ends_at' => 'immutable_datetime', 'approved_at' => 'immutable_datetime', 'is_public' => 'boolean', 'is_ticker' => 'boolean', 'duration_seconds' => 'integer', 'priority' => 'integer'];
    }

    public function screens(): BelongsToMany
    {
        return $this->belongsToMany(DisplayScreen::class, 'display_content_targets', 'content_id', 'screen_id');
    }

    public function announcement(): BelongsTo
    {
        return $this->belongsTo(Announcement::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}
