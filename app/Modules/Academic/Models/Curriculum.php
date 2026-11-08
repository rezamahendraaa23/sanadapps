<?php

namespace App\Modules\Academic\Models;

use App\Support\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Curriculum extends Model
{
    use HasPublicUuid;

    protected $table = 'curricula';

    protected $guarded = ['id', 'uuid'];

    protected $attributes = ['is_active' => true];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function phases(): HasMany
    {
        return $this->hasMany(CurriculumPhase::class)->orderBy('sort_order');
    }
}
