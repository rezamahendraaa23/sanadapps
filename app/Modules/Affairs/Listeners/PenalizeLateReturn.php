<?php

namespace App\Modules\Affairs\Listeners;

use App\Modules\Affairs\Actions\RecordViolation;
use App\Modules\Affairs\Models\DisciplineRecord;
use App\Modules\Affairs\Models\DisciplineRule;
use App\Modules\Core\Models\Module;
use App\Modules\Core\Support\ModuleRegistry;
use App\Modules\Leave\Events\LeaveEvent;

class PenalizeLateReturn
{
    public function __construct(private ModuleRegistry $modules, private RecordViolation $violations) {}

    /**
     * Opsi LEV-04: terlambat kembali otomatis menjadi poin bila pesantren memilih aturannya.
     */
    public function handle(LeaveEvent $event): void
    {
        if ($event->kind !== 'late' || ! $this->modules->enabled('discipline')) {
            return;
        }

        $ruleId = Module::query()->where('module_key', 'discipline')->value('settings')['late_rule_id'] ?? null;
        $rule = $ruleId ? DisciplineRule::query()->where('is_active', true)->find($ruleId) : null;

        if ($rule === null || DisciplineRecord::query()->where('leave_request_id', $event->leave->id)->exists()) {
            return;
        }

        $this->violations->handle($event->leave->student, $rule, now(config('sanad.display_timezone'))->toDateString(), ['notes' => 'Terlambat kembali dari izin.', 'leave_request_id' => $event->leave->id], null);
    }
}
