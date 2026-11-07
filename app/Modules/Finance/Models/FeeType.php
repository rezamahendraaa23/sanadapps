<?php

namespace App\Modules\Finance\Models;

use App\Modules\Core\Support\Audited;
use App\Support\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FeeType extends Model
{
    use Audited, HasPublicUuid;

    protected $guarded = ['id', 'uuid'];

    protected $attributes = ['is_mandatory' => true, 'allow_partial' => false, 'is_active' => true];

    protected function casts(): array
    {
        return ['is_mandatory' => 'boolean', 'allow_partial' => 'boolean', 'is_active' => 'boolean'];
    }

    public function rates(): HasMany
    {
        return $this->hasMany(FeeRate::class);
    }
}
