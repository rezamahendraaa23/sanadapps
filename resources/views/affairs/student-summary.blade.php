@php
    use App\Modules\Affairs\Actions\RecordViolation;
    use App\Modules\Affairs\Models\DisciplineRecord;
    use App\Modules\Affairs\Models\DutyAssignment;
    use App\Modules\Affairs\Models\HealthRecord;

    $registry = app(\App\Modules\Core\Support\ModuleRegistry::class);
    $records = $registry->enabled('discipline') ? DisciplineRecord::query()->with('rule:id,name')->where('student_id', $child->id)->latest('occurred_on')->limit(5)->get() : collect();
    $visits = $registry->enabled('health') && $isGuardian ? HealthRecord::query()->where('student_id', $child->id)->latest('visit_date')->limit(5)->get(['id', 'uuid', 'visit_date', 'outcome']) : collect();
    $duties = $registry->enabled('duty_roster') ? DutyAssignment::query()->with('roster')->where('assignee_type', 'student')->where('assignee_id', $child->id)->whereDate('duty_date', '>=', now(config('sanad.display_timezone'))->toDateString())->orderBy('duty_date')->limit(5)->get() : collect();
@endphp

@if ($registry->enabled('discipline') && ($records->isNotEmpty()))
    <x-ui.card title="Poin pelanggaran">
        <p class="font-display font-tabular text-2xl font-semibold">{{ RecordViolation::points($child) }} <span class="font-sans text-sm font-normal">poin tahun ini</span></p>
        @foreach ($records as $record)
            <p class="border-b border-border py-2 text-sm break-words last:border-b-0">{{ $record->occurred_on->translatedFormat('j M Y') }}: {{ $record->rule->name }}</p>
        @endforeach
    </x-ui.card>
@endif

@if ($visits->isNotEmpty())
    <x-ui.card title="Kunjungan UKS">
        @foreach ($visits as $visit)
            <p class="border-b border-border py-2 text-sm last:border-b-0">{{ $visit->visit_date->translatedFormat('j M Y') }}: {{ HealthRecord::OUTCOMES[$visit->outcome] }}</p>
        @endforeach
    </x-ui.card>
@endif

@if ($duties->isNotEmpty())
    <x-ui.card title="Jadwal piket">
        @foreach ($duties as $duty)
            <p class="border-b border-border py-2 text-sm break-words last:border-b-0">{{ $duty->duty_date->translatedFormat('l, j M') }}: {{ $duty->roster->name }}{{ $duty->roster->location ? ', '.$duty->roster->location : '' }}</p>
        @endforeach
    </x-ui.card>
@endif
