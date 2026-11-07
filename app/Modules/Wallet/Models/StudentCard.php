<?php

namespace App\Modules\Wallet\Models;

use App\Modules\Core\Models\Student;
use App\Modules\Core\Support\Audited;
use App\Support\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StudentCard extends Model
{
    use Audited, HasPublicUuid;

    /** @var list<string> */
    protected array $auditExcept = ['qr_secret', 'pin_hash', 'pin_failed_count', 'locked_until'];

    protected $guarded = ['id', 'uuid'];

    protected $hidden = ['qr_secret', 'pin_hash'];

    protected $attributes = ['status' => 'active', 'pin_failed_count' => 0];

    protected function casts(): array
    {
        return [
            'qr_secret' => 'encrypted',
            'pin_failed_count' => 'integer',
            'locked_until' => 'immutable_datetime',
            'issued_at' => 'immutable_datetime',
            'blocked_at' => 'immutable_datetime',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function scans(): HasMany
    {
        return $this->hasMany(CardScan::class, 'card_id');
    }

    public function isLocked(): bool
    {
        return $this->locked_until !== null && $this->locked_until->isFuture();
    }
}
