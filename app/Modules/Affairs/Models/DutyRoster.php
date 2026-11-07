<?php

namespace App\Modules\Affairs\Models;

use App\Support\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DutyRoster extends Model
{
    use HasPublicUuid;

    public const DAYS = [1 => 'Senin', 2 => 'Selasa', 3 => 'Rabu', 4 => 'Kamis', 5 => 'Jumat', 6 => 'Sabtu', 7 => 'Ahad'];

    protected $guarded = ['id', 'uuid'];

    protected function casts(): array
    {
        return ['day_of_week' => 'integer'];
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(DutyAssignment::class, 'roster_id');
    }
}
