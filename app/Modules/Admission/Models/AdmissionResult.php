<?php

namespace App\Modules\Admission\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdmissionResult extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['score' => 'float'];
    }

    public function step(): BelongsTo
    {
        return $this->belongsTo(AdmissionStep::class, 'step_id');
    }
}
