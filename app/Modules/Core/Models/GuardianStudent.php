<?php

namespace App\Modules\Core\Models;

use App\Support\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Relations\Pivot;

class GuardianStudent extends Pivot
{
    use HasPublicUuid;

    public $incrementing = true;

    protected $table = 'guardian_student';

    protected function casts(): array
    {
        return ['is_primary' => 'boolean', 'is_payer' => 'boolean', 'can_receive_wa' => 'boolean'];
    }
}
