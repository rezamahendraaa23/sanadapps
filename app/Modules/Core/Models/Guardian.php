<?php

namespace App\Modules\Core\Models;

use App\Modules\Core\Support\Audited;
use App\Support\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Guardian extends Model
{
    use Audited, HasPublicUuid, SoftDeletes;

    protected $guarded = ['id', 'uuid'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function students(): BelongsToMany
    {
        return $this->belongsToMany(Student::class)
            ->using(GuardianStudent::class)
            ->withPivot(['relation', 'is_primary', 'is_payer', 'can_receive_wa'])
            ->withTimestamps();
    }
}
