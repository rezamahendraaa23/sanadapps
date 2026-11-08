<?php

namespace App\Modules\Academic\Livewire;

use App\Modules\Academic\Models\AttendanceRecord;
use App\Modules\Academic\Models\PhaseResult;
use App\Modules\Academic\Models\ReportCard;
use App\Modules\Academic\Models\Schedule;
use App\Modules\Academic\Support\AttendanceStats;
use App\Modules\Academic\Support\StudentSchedule;
use App\Modules\Core\Models\Student;
use App\Modules\Core\Support\ModuleRegistry;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Perkembangan anak bagi wali (W-09, W-10) dan jadwal, nilai, absensi bagi santri (S-02, S-08).
 * Santri selalu diambil dari relasi pengguna, jadi uuid milik santri lain tidak pernah cocok.
 */
#[Title('Perkembangan')]
class StudentAcademics extends Component
{
    #[Url(as: 'anak', except: '')]
    public string $childUuid = '';

    #[Url(as: 'hari', except: 0)]
    public int $day = 0;

    #[Url(except: 'jadwal')]
    public string $segment = 'jadwal';

    private function children()
    {
        $user = auth()->user();

        return $user->type === 'guardian'
            ? ($user->guardian?->students()->orderBy('name')->get() ?? collect())
            : collect(array_filter([$user->student]));
    }

    private function child(): ?Student
    {
        $children = $this->children();

        return $children->firstWhere('uuid', $this->childUuid) ?? $children->first();
    }

    public function render(ModuleRegistry $modules): View
    {
        $user = auth()->user();
        $isGuardian = $user->type === 'guardian';
        $child = $this->child();
        $today = StudentSchedule::todayIso();
        $selected = $this->day >= 1 && $this->day <= 7 ? $this->day : $today;
        $zone = config('sanad.display_timezone');
        $start = now($zone)->startOfMonth()->toDateString();
        $end = now($zone)->toDateString();

        return view('academic.student-academics', [
            'isGuardian' => $isGuardian,
            'children' => $this->children(),
            'child' => $child,
            'segments' => array_filter([
                'jadwal' => $modules->enabled('academic') ? 'Jadwal' : null,
                'absensi' => $modules->enabled('attendance') ? 'Absensi' : null,
                'nilai' => $modules->enabled('grading') ? 'Nilai' : null,
                'fase' => $modules->enabled('curriculum') ? 'Rapor Fase' : null,
            ]),
            'schedules' => $child ? StudentSchedule::forStudent($child, $selected) : collect(),
            'days' => Schedule::DAYS,
            'selected' => $selected,
            'isToday' => $selected === $today,
            'time' => now($zone)->format('H:i:s'),
            'attendance' => $child ? AttendanceStats::perStudent(null, null, $start, $end, [$child->id])->first() : null,
            'recent' => $child ? AttendanceRecord::query()->with('session:id,title,session_date,closed_at')->where('student_id', $child->id)->whereHas('session', fn ($query) => $query->whereNotNull('closed_at'))->latest('id')->limit(15)->get() : collect(),
            // Hanya fase yang sudah diputuskan lulus atau belum; yang masih berjalan belum punya rapor.
            'phaseResults' => $child && $modules->enabled('curriculum') ? PhaseResult::query()->with('phase.curriculum')->where('student_id', $child->id)->where('status', '!=', 'pending')->orderByDesc('started_on')->get() : collect(),
            'cards' => $child ? ReportCard::query()->with(['term:id,name', 'items.subject:id,name,kkm'])->where('student_id', $child->id)->where('status', 'published')->latest('id')->get() : collect(),
        ])->layout('components.layouts.portal', $isGuardian ? ['role' => 'wali', 'active' => 'progress', 'title' => 'Perkembangan'] : ['role' => 'santri', 'active' => 'schedule', 'title' => 'Jadwal & Nilai']);
    }
}
