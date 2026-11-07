<?php

namespace App\Modules\Admission\Models;

use App\Modules\Core\Models\Student;
use App\Modules\Core\Support\Audited;
use App\Modules\Exam\Models\ExamAttempt;
use App\Modules\Finance\Models\Invoice;
use App\Support\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Applicant extends Model
{
    use Audited, HasPublicUuid;

    public const STATUSES = [
        'draft' => 'Draf',
        'submitted' => 'Menunggu pembayaran',
        'paid' => 'Sudah bayar',
        'verified' => 'Dokumen terverifikasi',
        'in_selection' => 'Dalam seleksi',
        'accepted' => 'Diterima',
        'rejected' => 'Tidak diterima',
        'waitlist' => 'Cadangan',
        'enrolled' => 'Sudah daftar ulang',
    ];

    public const DOCUMENTS = ['kk' => 'Kartu Keluarga', 'akta' => 'Akta kelahiran', 'foto' => 'Pas foto', 'ijazah' => 'Ijazah terakhir'];

    public const REQUIRED_DOCUMENTS = ['kk', 'akta', 'foto'];

    public const SESSION_KEY = 'ppdb_applicant';

    protected $guarded = ['id', 'uuid'];

    protected $attributes = ['status' => 'draft', 'guardian_relation' => 'ayah', 'extra' => '{}'];

    protected function casts(): array
    {
        return ['birth_date' => 'date', 'extra' => 'array', 'total_score' => 'float', 'consent_at' => 'immutable_datetime', 'submitted_at' => 'immutable_datetime', 'announced_at' => 'immutable_datetime'];
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(AdmissionPeriod::class, 'period_id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(ApplicantDocument::class);
    }

    public function results(): HasMany
    {
        return $this->hasMany(AdmissionResult::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function invoice(): HasOne
    {
        return $this->hasOne(Invoice::class)->latestOfMany();
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(ExamAttempt::class);
    }

    public static function current(): ?self
    {
        $uuid = session(self::SESSION_KEY);

        return $uuid ? self::query()->where('uuid', $uuid)->first() : null;
    }

    /**
     * Keputusan baru terlihat pendaftar setelah diumumkan (PPD-06).
     */
    public function publicStatus(): string
    {
        return in_array($this->status, ['accepted', 'rejected', 'waitlist'], true) && $this->announced_at === null ? 'in_selection' : $this->status;
    }
}
