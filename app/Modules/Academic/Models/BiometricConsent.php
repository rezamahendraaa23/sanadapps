<?php

namespace App\Modules\Academic\Models;

use App\Modules\Core\Models\Guardian;
use App\Modules\Core\Models\Student;
use App\Support\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BiometricConsent extends Model
{
    use HasPublicUuid;

    protected $guarded = ['id', 'uuid'];

    protected function casts(): array
    {
        return ['granted_at' => 'immutable_datetime', 'revoked_at' => 'immutable_datetime'];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function guardian(): BelongsTo
    {
        return $this->belongsTo(Guardian::class);
    }

    /**
     * @param  Builder<BiometricConsent>  $query
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('revoked_at')->where('scope', 'face_attendance');
    }
}
