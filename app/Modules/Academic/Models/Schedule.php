<?php

namespace App\Modules\Academic\Models;

use App\Modules\Core\Models\Classroom;
use App\Modules\Core\Models\Teacher;
use App\Modules\Core\Models\Term;
use App\Modules\Core\Support\Audited;
use App\Support\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Schedule extends Model
{
    use Audited, HasPublicUuid;

    public const DAYS = [1 => 'Senin', 2 => 'Selasa', 3 => 'Rabu', 4 => 'Kamis', 5 => 'Jumat', 6 => 'Sabtu', 7 => 'Ahad'];

    public const ACTIVITIES = ['kbm' => 'KBM', 'sholat' => 'Sholat', 'halaqah' => 'Halaqah', 'makan' => 'Makan', 'tidur' => 'Tidur', 'kegiatan' => 'Kegiatan'];

    protected $guarded = ['id', 'uuid'];

    protected $attributes = ['activity_type' => 'kbm'];

    public function term(): BelongsTo
    {
        return $this->belongsTo(Term::class);
    }

    public function classroom(): BelongsTo
    {
        return $this->belongsTo(Classroom::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class);
    }

    public function label(): string
    {
        return $this->subject?->name ?? $this->title ?? self::ACTIVITIES[$this->activity_type];
    }

    public function startLabel(): string
    {
        return str_replace(':', '.', substr((string) $this->start_time, 0, 5));
    }

    public function endLabel(): string
    {
        return str_replace(':', '.', substr((string) $this->end_time, 0, 5));
    }
}
