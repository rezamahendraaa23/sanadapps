<?php

namespace App\Modules\Core\Models;

use App\Modules\Core\Support\Audited;
use App\Support\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AcademicYear extends Model
{
    use Audited, HasPublicUuid;

    protected $guarded = ['id', 'uuid'];

    protected function casts(): array
    {
        return ['start_date' => 'date', 'end_date' => 'date', 'is_active' => 'boolean'];
    }

    public function terms(): HasMany
    {
        return $this->hasMany(Term::class);
    }
}
