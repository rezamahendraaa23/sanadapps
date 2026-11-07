<?php

namespace App\Modules\Affairs\Models;

use App\Modules\Core\Models\Student;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentRiskScore extends Model
{
    public const LEVELS = ['high' => 'Tinggi', 'medium' => 'Sedang', 'low' => 'Rendah'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['computed_on' => 'date', 'reasons' => 'array', 'score' => 'integer'];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}
