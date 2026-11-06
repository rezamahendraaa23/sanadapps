<?php

namespace App\Modules\Core\Models;

use App\Modules\Core\Support\Audited;
use App\Support\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Student extends Model
{
    use Audited, HasPublicUuid, SoftDeletes;

    protected $guarded = ['id', 'uuid'];

    protected $attributes = ['extra' => '{}'];

    protected function casts(): array
    {
        return ['birth_date' => 'date', 'entry_date' => 'date', 'extra' => 'array'];
    }

    public function classroom(): BelongsTo
    {
        return $this->belongsTo(Classroom::class);
    }

    public function dormitory(): BelongsTo
    {
        return $this->belongsTo(Dormitory::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function guardians(): BelongsToMany
    {
        return $this->belongsToMany(Guardian::class)
            ->using(GuardianStudent::class)
            ->withPivot(['relation', 'is_primary', 'is_payer', 'can_receive_wa'])
            ->withTimestamps();
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(StudentEnrollment::class);
    }
}
