<?php

namespace App\Modules\Masjid\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class DisplayConsent extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['granted_at' => 'immutable_datetime', 'revoked_at' => 'immutable_datetime'];
    }

    /**
     * @param  Builder<DisplayConsent>  $query
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('revoked_at');
    }
}
