<?php

namespace App\Modules\Academic\Livewire;

use App\Modules\Academic\Actions\OpenAttendanceSession;
use App\Modules\Academic\Models\AttendanceSession;
use App\Modules\Academic\Models\Schedule;
use App\Modules\Academic\Models\TeachingScope;
use App\Modules\Academic\Support\AcademicException;
use App\Modules\Academic\Support\AcademicSettings;
use App\Modules\Academic\Support\StudentSchedule;
use App\Modules\Core\Models\Dormitory;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Daftar sesi hari ini. Dipakai di panel, portal ustadz (U-03), dan portal musyrif (U-13).
 */
#[Title('Absensi')]
class AttendanceToday extends Component
{
    public string $dorm_kind = 'asrama';

    public string $dorm_title = '';

    public string $dorm_id = '';

    public string $dorm_time = '';

    public string $dorm_source = 'manual';

    public string $class_source = 'manual';

    private function schedules()
    {
        $user = auth()->user();
        $term = StudentSchedule::activeTerm();

        if ($term === null) {
            return collect();
        }

        return Schedule::query()->with(['subject:id,name', 'classroom:id,name', 'teacher:id,name,user_id'])
            ->where('term_id', $term->id)->where('day_of_week', StudentSchedule::todayIso())->whereNotNull('classroom_id')
            ->when(! $user->can('academic.manage'), fn ($query) => $query->where(fn ($inner) => $inner
                ->where('teacher_id', TeachingScope::teacher($user)?->id ?? 0)))
            ->orderBy('start_time')->get();
    }

    private function dormitories()
    {
        $user = auth()->user();

        return Dormitory::query()->when(! $user->can('academic.manage'), fn ($query) => $query->where('musyrif_user_id', $user->id))->orderBy('name')->get();
    }

    private function today(): string
    {
        return now(config('sanad.display_timezone'))->toDateString();
    }

    public function openSchedule(string $uuid, OpenAttendanceSession $action)
    {
        $schedule = $this->schedules()->firstWhere('uuid', $uuid);
        abort_if($schedule === null, 403);

        try {
            $session = $action->fromSchedule($schedule, $this->today(), $this->class_source, auth()->id());
        } catch (AcademicException $exception) {
            $this->dispatch('toast', message: $exception->getMessage(), tone: 'danger');

            return null;
        }

        return $this->redirect(self::sessionUrl($session), navigate: true);
    }

    public function openDormitory(OpenAttendanceSession $action)
    {
        $this->resetErrorBag();
        $dormitory = $this->dormitories()->firstWhere('id', (int) $this->dorm_id);

        if ($dormitory === null) {
            $this->addError('dorm_id', 'Pilih asrama.');

            return null;
        }

        $this->validate(['dorm_kind' => 'required|in:asrama,sholat,kegiatan', 'dorm_title' => 'required|string|max:100', 'dorm_time' => 'required|date_format:H:i'], [], ['dorm_title' => 'nama sesi', 'dorm_time' => 'jam mulai']);

        try {
            $session = $action->forDormitory($dormitory->id, $this->dorm_kind, $this->dorm_title, $this->today(), $this->dorm_time, $this->dorm_source, auth()->id());
        } catch (AcademicException $exception) {
            $this->addError('dorm_source', $exception->getMessage());

            return null;
        }

        return $this->redirect(self::sessionUrl($session), navigate: true);
    }

    public static function sessionUrl(AttendanceSession $session): string
    {
        $user = auth()->user();

        if ($user->can('academic.manage')) {
            return route('staff.attendance.session', $session);
        }

        return route($session->dormitory_id ? 'musyrif.attendance.session' : 'ustadz.attendance.session', $session);
    }

    public function render(): View
    {
        $sessions = AttendanceSession::query()->withCount('records')->whereDate('session_date', $this->today())->latest('id')->get()
            ->filter(fn (AttendanceSession $session) => $session->canBeTakenBy(auth()->user()));

        $user = auth()->user();
        $role = $user->can('academic.manage') ? null : ($user->hasRole('musyrif') ? 'musyrif' : 'ustadz');

        return view('academic.attendance-today', [
            'schedules' => $this->schedules(),
            'openedBySchedule' => $sessions->whereNotNull('schedule_id')->keyBy('schedule_id'),
            'dormSessions' => $sessions->whereNull('schedule_id'),
            'dormitories' => $this->dormitories(),
            'modes' => array_intersect_key(['manual' => 'Manual', 'card' => 'Tap kartu', 'qr' => 'Pindai QR'], array_flip(AcademicSettings::verificationModes())),
            'hasTerm' => StudentSchedule::activeTerm() !== null,
        ])->layout($role ? 'components.layouts.portal' : 'components.layouts.staff', $role ? ['role' => $role, 'active' => 'attendance', 'title' => 'Absensi'] : ['active' => 'attendance']);
    }
}
