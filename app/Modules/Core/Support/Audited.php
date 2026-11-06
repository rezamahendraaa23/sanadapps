<?php

namespace App\Modules\Core\Support;

use App\Modules\Core\Actions\RecordAudit;

/**
 * Mencatat perubahan model ke audit_logs. Kolom di $auditExcept tidak pernah ikut tercatat.
 */
trait Audited
{
    public static function bootAudited(): void
    {
        static::created(fn ($model) => $model->writeAudit('created', null, $model->auditableValues($model->getAttributes())));

        static::updated(function ($model): void {
            $changed = array_keys($model->getChanges());
            $after = $model->auditableValues($model->getChanges());

            if ($after === []) {
                return;
            }

            $model->writeAudit('updated', $model->auditableValues(array_intersect_key($model->getOriginal(), array_flip($changed))), $after);
        });

        static::deleted(fn ($model) => $model->writeAudit('deleted', $model->auditableValues($model->getOriginal()), null));
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    protected function auditableValues(array $values): array
    {
        $hidden = [...($this->auditExcept ?? []), 'password', 'remember_token', 'updated_at', 'created_at', 'two_factor_secret', 'two_factor_recovery_codes'];

        return array_diff_key($values, array_flip($hidden));
    }

    /**
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>|null  $after
     */
    protected function writeAudit(string $action, ?array $before, ?array $after): void
    {
        app(RecordAudit::class)->handle($action, class_basename($this), $this->getKey(), $before, $after);
    }
}
