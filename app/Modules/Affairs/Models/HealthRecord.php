<?php

namespace App\Modules\Affairs\Models;

use App\Modules\Core\Models\Student;
use App\Support\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HealthRecord extends Model
{
    use HasPublicUuid;

    public const OUTCOMES = ['ditangani' => 'Ditangani', 'rujuk' => 'Dirujuk', 'dipulangkan' => 'Dipulangkan'];

    protected $guarded = ['id', 'uuid'];

    protected $hidden = ['complaint', 'action', 'medication'];

    protected function casts(): array
    {
        return ['visit_date' => 'date', 'complaint' => 'encrypted', 'action' => 'encrypted', 'medication' => 'encrypted'];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}
