<?php

namespace App\Modules\Affairs\Models;

use App\Modules\Core\Models\Student;
use App\Modules\Core\Support\Audited;
use App\Support\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DisciplineRecord extends Model
{
    use Audited, HasPublicUuid;

    protected $guarded = ['id', 'uuid'];

    protected function casts(): array
    {
        return ['occurred_on' => 'date', 'points' => 'integer'];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function rule(): BelongsTo
    {
        return $this->belongsTo(DisciplineRule::class, 'rule_id');
    }
}
