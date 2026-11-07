<?php

namespace App\Modules\Leave\Models;

use App\Modules\Core\Models\Student;
use App\Modules\Core\Models\User;
use App\Modules\Wallet\Models\Device;
use App\Support\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class GateLog extends Model
{
    use HasPublicUuid;

    public const RESULTS = ['ok' => 'Berhasil', 'denied' => 'Ditolak', 'override' => 'Darurat'];

    public const METHODS = ['card' => 'Kartu', 'qr' => 'QR', 'face' => 'Wajah', 'manual' => 'Manual'];

    public $timestamps = false;

    protected $guarded = ['id', 'uuid'];

    protected function casts(): array
    {
        return ['occurred_at' => 'immutable_datetime'];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function leave(): BelongsTo
    {
        return $this->belongsTo(LeaveRequest::class, 'leave_request_id');
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    public function review(): HasOne
    {
        return $this->hasOne(GateLogReview::class);
    }
}
