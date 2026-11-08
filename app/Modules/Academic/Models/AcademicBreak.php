<?php

namespace App\Modules\Academic\Models;

use App\Support\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;

class AcademicBreak extends Model
{
    use HasPublicUuid;

    public const KINDS = ['special' => 'Program khusus', 'holiday' => 'Libur'];

    protected $guarded = ['id', 'uuid'];

    protected function casts(): array
    {
        return ['starts_on' => 'date', 'ends_on' => 'date'];
    }

    public function days(): int
    {
        return (int) $this->starts_on->diffInDays($this->ends_on) + 1;
    }
}
