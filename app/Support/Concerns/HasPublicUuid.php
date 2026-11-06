<?php

namespace App\Support\Concerns;

use Illuminate\Support\Str;

/**
 * Id angka hanya untuk internal; URL memakai uuid agar tidak bisa ditebak (PRD Sistem 6.1).
 */
trait HasPublicUuid
{
    public static function bootHasPublicUuid(): void
    {
        static::creating(function ($model): void {
            $model->uuid ??= (string) Str::uuid7();
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }
}
