<?php

namespace App\Modules\Exam\Models;

use App\Modules\Core\Models\Student;
use App\Support\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExamAttempt extends Model
{
    use HasPublicUuid;

    public const STATUSES = ['in_progress' => 'Mengerjakan', 'submitted' => 'Dikumpulkan', 'auto_submitted' => 'Terkumpul otomatis', 'graded' => 'Selesai dinilai'];

    protected $guarded = ['id', 'uuid'];

    protected $hidden = ['session_token'];

    protected $attributes = ['status' => 'in_progress', 'blur_count' => 0, 'attempt_no' => 1];

    protected function casts(): array
    {
        return [
            'started_at' => 'immutable_datetime',
            'deadline_at' => 'immutable_datetime',
            'submitted_at' => 'immutable_datetime',
            'last_seen_at' => 'immutable_datetime',
            'question_order' => 'array',
            'option_order' => 'array',
            'score' => 'float',
            'blur_count' => 'integer',
        ];
    }

    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function answers(): HasMany
    {
        return $this->hasMany(ExamAnswer::class, 'attempt_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(ExamEvent::class, 'attempt_id');
    }

    public function isOpen(): bool
    {
        return $this->status === 'in_progress';
    }

    /**
     * Status bisa berubah menjadi graded setelah dinilai, jadi "otomatis" dibaca dari waktunya.
     */
    public function wasAutoSubmitted(): bool
    {
        return $this->submitted_at !== null && $this->submitted_at->greaterThanOrEqualTo($this->deadline_at);
    }

    public function showsResult(): bool
    {
        return match ($this->exam->show_result_mode) {
            'immediately' => true,
            'after_close' => $this->exam->status === 'closed',
            default => false,
        };
    }
}
