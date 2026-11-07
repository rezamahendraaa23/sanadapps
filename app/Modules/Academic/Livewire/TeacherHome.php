<?php

namespace App\Modules\Academic\Livewire;

use App\Modules\Academic\Models\AttendanceSession;
use App\Modules\Academic\Models\TeachingScope;
use App\Modules\Academic\Support\StudentSchedule;
use App\Modules\Core\Models\Dormitory;
use App\Modules\Core\Models\Student;
use App\Modules\Leave\Models\LeaveRequest;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Beranda (U-01) dan jadwal mengajar (U-02) ustadz, sekaligus beranda musyrif.
 */
#[Title('Beranda')]
class TeacherHome extends Component
{
    #[Url(as: 'hari', except: 0)]
    public int $day = 0;

    public function render(): View
    {
        $user = auth()->user();
        $teacher = TeachingScope::teacher($user);
        $today = StudentSchedule::todayIso();
        $selected = $this->day >= 1 && $this->day <= 7 ? $this->day : $today;
        $schedules = $teacher ? StudentSchedule::forTeacher($teacher, $selected) : collect();
        $time = now(config('sanad.display_timezone'))->format('H:i:s');
        $role = $user->hasRole('musyrif') && ! $user->hasRole('ustadz') ? 'musyrif' : 'ustadz';

        return view('academic.teacher-home', [
            'user' => $user,
            'schedules' => $schedules,
            'next' => $selected === $today ? $schedules->first(fn ($schedule) => (string) $schedule->end_time >= $time) : null,
            'selected' => $selected,
            'today' => $today,
            'openSessions' => AttendanceSession::query()->whereDate('session_date', now(config('sanad.display_timezone'))->toDateString())->whereNull('closed_at')->get()->filter(fn ($session) => $session->canBeTakenBy($user))->count(),
            'dormitories' => Dormitory::query()->where('musyrif_user_id', $user->id)->withCount('students')->get(),
            'role' => $role,
            'pendingLeaves' => $role === 'musyrif' ? LeaveRequest::query()->where('status', 'pending')->whereIn('student_id', Student::query()->visibleTo($user)->select('students.id'))->count() : 0,
        ])->layout('components.layouts.portal', ['role' => $role, 'active' => 'home', 'title' => 'Beranda']);
    }
}
