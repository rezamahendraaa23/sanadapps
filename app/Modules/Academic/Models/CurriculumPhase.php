<?php

namespace App\Modules\Academic\Models;

use App\Modules\Core\Models\Classroom;
use App\Support\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CurriculumPhase extends Model
{
    use HasPublicUuid;

    protected $guarded = ['id', 'uuid'];

    protected $attributes = ['has_certificate' => true, 'min_score' => 70];

    protected function casts(): array
    {
        return ['sort_order' => 'integer', 'duration_weeks' => 'integer', 'min_score' => 'float', 'has_certificate' => 'boolean'];
    }

    public function curriculum(): BelongsTo
    {
        return $this->belongsTo(Curriculum::class);
    }

    public function classrooms(): HasMany
    {
        return $this->hasMany(Classroom::class, 'phase_id');
    }

    public function next(): ?self
    {
        return static::query()->where('curriculum_id', $this->curriculum_id)->where('sort_order', '>', $this->sort_order)->orderBy('sort_order')->first();
    }

    public function durationLabel(): string
    {
        return $this->duration_weeks % 4 === 0 ? ($this->duration_weeks / 4).' bulan' : $this->duration_weeks.' minggu';
    }
}
