<?php

namespace App\Modules\Academic\Models;

use App\Modules\Academic\Support\AcademicSettings;
use App\Modules\Core\Models\Classroom;
use App\Modules\Core\Models\Dormitory;
use App\Modules\Core\Models\User;
use App\Support\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AttendanceSession extends Model
{
    use HasPublicUuid;

    public const KINDS = ['kelas' => 'Kelas', 'asrama' => 'Asrama', 'sholat' => 'Sholat', 'kegiatan' => 'Kegiatan'];

    protected $guarded = ['id', 'uuid'];

    protected $attributes = ['source' => 'manual', 'kind' => 'kelas'];

    protected function casts(): array
    {
        return ['session_date' => 'date', 'allowed_modes' => 'array', 'closed_at' => 'immutable_datetime'];
    }

    /**
     * Cara absen yang bisa dipakai sekarang. Selama sesi terbuka, cara yang baru diaktifkan pesantren
     * (misalnya wajah) langsung ikut, jadi tidak perlu membuka sesi ulang.
     *
     * @return list<string>
     */
    public function modes(): array
    {
        $stored = $this->allowed_modes ?? [$this->source];
        if ($this->isClosed()) {
            return $stored;
        }

        return array_values(array_unique([...$stored, ...array_keys(AcademicSettings::modeOptions())]));
    }

    public function records(): HasMany
    {
        return $this->hasMany(AttendanceRecord::class, 'session_id');
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(Schedule::class);
    }

    public function classroom(): BelongsTo
    {
        return $this->belongsTo(Classroom::class);
    }

    public function dormitory(): BelongsTo
    {
        return $this->belongsTo(Dormitory::class);
    }

    public function isClosed(): bool
    {
        return $this->closed_at !== null;
    }

    /**
     * Pengajar sesi, wali kelasnya, musyrif asramanya, atau pengelola akademik.
     */
    public function canBeTakenBy(User $user): bool
    {
        if ($user->can('academic.manage')) {
            return true;
        }

        if (! $user->can('attendance.take')) {
            return false;
        }

        if ($this->dormitory_id !== null) {
            return $this->dormitory?->musyrif_user_id === $user->id;
        }

        return $this->schedule?->teacher?->user_id === $user->id
            || $this->classroom?->homeroomTeacher?->user_id === $user->id
            || ($this->classroom_id !== null && in_array($this->classroom_id, TeachingScope::classroomIds($user), true));
    }
}
