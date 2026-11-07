<?php

namespace App\Modules\Admission\Models;

use App\Modules\Core\Support\Audited;
use App\Support\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AdmissionPeriod extends Model
{
    use Audited, HasPublicUuid;

    protected $guarded = ['id', 'uuid'];

    protected $attributes = ['status' => 'draft', 'registration_fee' => 0];

    protected function casts(): array
    {
        return ['open_at' => 'immutable_datetime', 'close_at' => 'immutable_datetime', 'announce_at' => 'immutable_datetime', 'announced_at' => 'immutable_datetime', 'quota' => 'integer', 'registration_fee' => 'integer'];
    }

    public function steps(): HasMany
    {
        return $this->hasMany(AdmissionStep::class, 'period_id')->orderBy('sort_order')->orderBy('id');
    }

    public function applicants(): HasMany
    {
        return $this->hasMany(Applicant::class, 'period_id');
    }

    public function acceptedCount(): int
    {
        return $this->applicants()->whereIn('status', ['accepted', 'enrolled'])->count();
    }

    /**
     * Form tutup sendiri saat tanggal lewat atau kuota terisi (PPD-01).
     */
    public function isAccepting(): bool
    {
        return $this->status === 'open' && $this->open_at->lessThanOrEqualTo(now()) && $this->close_at->greaterThan(now()) && $this->acceptedCount() < $this->quota;
    }

    public static function accepting(): ?self
    {
        $period = self::query()->where('status', 'open')->first();

        return $period?->isAccepting() ? $period : null;
    }
}
