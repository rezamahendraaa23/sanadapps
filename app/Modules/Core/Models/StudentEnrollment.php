<?php

namespace App\Modules\Core\Models;

use App\Support\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentEnrollment extends Model
{
    use HasPublicUuid;

    protected $guarded = ['id', 'uuid'];

    protected function casts(): array
    {
        return ['started_at' => 'immutable_datetime', 'ended_at' => 'immutable_datetime'];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function classroom(): BelongsTo
    {
        return $this->belongsTo(Classroom::class);
    }
}
