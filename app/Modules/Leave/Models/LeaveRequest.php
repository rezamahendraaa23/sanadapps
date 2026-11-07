<?php

namespace App\Modules\Leave\Models;

use App\Modules\Core\Models\Student;
use App\Modules\Core\Models\User;
use App\Modules\Core\Support\Audited;
use App\Support\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LeaveRequest extends Model
{
    use Audited, HasPublicUuid;

    public const TYPES = ['pulang' => 'Pulang', 'keluar' => 'Keluar sebentar', 'sakit' => 'Sakit', 'kegiatan' => 'Kegiatan'];

    public const STATUSES = ['pending' => 'Menunggu', 'approved' => 'Disetujui', 'rejected' => 'Ditolak', 'out' => 'Sedang di luar', 'returned' => 'Sudah kembali', 'late' => 'Terlambat', 'cancelled' => 'Dibatalkan'];

    /** @var list<string> */
    protected array $auditExcept = ['qr_token'];

    protected $guarded = ['id', 'uuid'];

    protected $hidden = ['qr_token'];

    protected $attributes = ['status' => 'pending'];

    protected function casts(): array
    {
        return [
            'start_at' => 'immutable_datetime',
            'end_at' => 'immutable_datetime',
            'approved_at' => 'immutable_datetime',
            'out_at' => 'immutable_datetime',
            'returned_at' => 'immutable_datetime',
            'late_notified_at' => 'immutable_datetime',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(GateLog::class);
    }

    /**
     * Santri yang masih di luar. Izin terlambat yang sudah kembali juga berstatus late, jadi returned_at ikut diperiksa.
     *
     * @param  Builder<LeaveRequest>  $query
     */
    public function scopeOutside(Builder $query): Builder
    {
        return $query->whereIn('status', ['out', 'late'])->whereNull('returned_at');
    }

    public function isOutside(): bool
    {
        return in_array($this->status, ['out', 'late'], true) && $this->returned_at === null;
    }

    /**
     * Musyrif asramanya atau pengelola izin.
     */
    public function canBeDecidedBy(User $user): bool
    {
        if ($user->can('leave.manage')) {
            return true;
        }

        return $user->can('leave.decide') && $this->student?->dormitory?->musyrif_user_id === $user->id;
    }

    public function statusLabel(): string
    {
        if ($this->status === 'late' && $this->returned_at !== null) {
            return 'Kembali terlambat';
        }

        return self::STATUSES[$this->status];
    }
}
