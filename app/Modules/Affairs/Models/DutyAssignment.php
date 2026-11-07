<?php

namespace App\Modules\Affairs\Models;

use App\Modules\Core\Models\Student;
use App\Modules\Core\Models\Teacher;
use App\Support\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DutyAssignment extends Model
{
    use HasPublicUuid;

    protected $guarded = ['id', 'uuid'];

    protected $attributes = ['status' => 'scheduled'];

    protected function casts(): array
    {
        return ['duty_date' => 'date'];
    }

    public function roster(): BelongsTo
    {
        return $this->belongsTo(DutyRoster::class, 'roster_id');
    }

    public function assigneeName(): string
    {
        return (string) ($this->assignee_type === 'student' ? Student::query()->whereKey($this->assignee_id)->value('name') : Teacher::query()->whereKey($this->assignee_id)->value('name'));
    }
}
