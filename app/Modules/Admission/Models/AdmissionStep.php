<?php

namespace App\Modules\Admission\Models;

use App\Modules\Exam\Models\Exam;
use App\Support\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdmissionStep extends Model
{
    use HasPublicUuid;

    public const TYPES = ['document' => 'Dokumen', 'exam' => 'Ujian online', 'interview' => 'Wawancara'];

    protected $guarded = ['id', 'uuid'];

    protected function casts(): array
    {
        return ['weight' => 'integer', 'sort_order' => 'integer'];
    }

    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class);
    }
}
