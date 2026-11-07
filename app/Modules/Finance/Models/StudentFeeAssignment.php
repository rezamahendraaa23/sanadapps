<?php

namespace App\Modules\Finance\Models;

use App\Modules\Core\Models\Student;
use App\Modules\Core\Support\Audited;
use App\Support\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentFeeAssignment extends Model
{
    use Audited, HasPublicUuid;

    protected $guarded = ['id', 'uuid'];

    protected $attributes = ['is_active' => true];

    protected function casts(): array
    {
        return ['custom_amount' => 'integer', 'start_month' => 'date', 'end_month' => 'date', 'is_active' => 'boolean'];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function feeType(): BelongsTo
    {
        return $this->belongsTo(FeeType::class);
    }
}
