<?php

namespace App\Modules\Academic\Models;

use App\Modules\Core\Models\Student;
use App\Support\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceRecord extends Model
{
    use HasPublicUuid;

    public const STATUSES = ['hadir' => 'Hadir', 'izin' => 'Izin', 'sakit' => 'Sakit', 'alpa' => 'Alpa', 'terlambat' => 'Terlambat'];

    protected $guarded = ['id', 'uuid'];

    protected $attributes = ['status' => 'hadir', 'method' => 'manual', 'review_status' => 'none'];

    protected function casts(): array
    {
        return ['scanned_at' => 'immutable_datetime', 'reviewed_at' => 'immutable_datetime', 'match_score' => 'decimal:3', 'sampled' => 'boolean'];
    }

    /**
     * Ditandai, zona abu-abu, atau sampel acak yang belum diputuskan.
     *
     * @param  Builder<AttendanceRecord>  $query
     */
    public function scopeAwaitingReview(Builder $query): Builder
    {
        return $query->where(fn (Builder $inner) => $inner->whereIn('review_status', ['flagged', 'pending'])->orWhere(fn (Builder $sample) => $sample->where('sampled', true)->where('review_status', 'none')));
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(AttendanceSession::class, 'session_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}
