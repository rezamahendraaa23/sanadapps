<?php

namespace App\Modules\Tahfidz\Models;

use App\Modules\Core\Models\Student;
use App\Modules\Core\Models\User;
use App\Modules\Core\Support\Audited;
use App\Support\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TahfidzRecord extends Model
{
    use Audited, HasPublicUuid;

    public const TYPES = ['ziyadah' => 'Ziyadah', 'murojaah' => 'Murojaah', 'tahsin' => 'Tahsin'];

    public const GRADES = ['mumtaz' => 'Mumtaz', 'jayyid_jiddan' => 'Jayyid jiddan', 'jayyid' => 'Jayyid', 'maqbul' => 'Maqbul', 'rasib' => 'Rasib'];

    protected $guarded = ['id', 'uuid'];

    protected function casts(): array
    {
        return ['record_date' => 'date', 'pages' => 'float'];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function surahFrom(): BelongsTo
    {
        return $this->belongsTo(QuranSurah::class, 'surah_from');
    }

    public function surahTo(): BelongsTo
    {
        return $this->belongsTo(QuranSurah::class, 'surah_to');
    }
}
