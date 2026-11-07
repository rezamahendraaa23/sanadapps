<?php

namespace App\Modules\Academic\Models;

use App\Modules\Core\Models\Student;
use App\Support\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FaceEnrollment extends Model
{
    use HasPublicUuid;

    protected $guarded = ['id', 'uuid'];

    protected $hidden = ['embedding'];

    protected function casts(): array
    {
        return ['embedding' => 'encrypted:array', 'quality_score' => 'float', 'enrolled_at' => 'immutable_datetime', 'revoked_at' => 'immutable_datetime'];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function consent(): BelongsTo
    {
        return $this->belongsTo(BiometricConsent::class, 'consent_id');
    }
}
