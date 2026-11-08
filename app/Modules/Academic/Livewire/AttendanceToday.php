<?php

namespace App\Modules\Academic\Livewire;

use App\Modules\Academic\Actions\OpenAttendanceSession;
use App\Modules\Academic\Actions\ReviewAttendance;
use App\Modules\Academic\Models\AttendanceSession;
use App\Modules\Academic\Models\Schedule;
use App\Modules\Academic\Models\TeachingScope;
use App\Modules\Academic\Support\AcademicException;
use App\Modules\Academic\Support\AcademicSettings;
use App\Modules\Academic\Support\StudentSchedule;
use App\Modules\Core\Models\Classroom;
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
    /** Peserta sesi tanpa jadwal: all, c:<id kelas>, atau d:<id asrama>. */
    public string $target = '';

    public string $dorm_kind = 'sholat';

    public string $dorm_title = 'Sholat Berjamaah';

    private const TITLES = ['sholat' => 'Sholat Berjamaah', 'asrama' => 'Apel Asrama', 'kegiatan' => 'Kegiatan'];

    public function mount(): void
    {
        // Musyrif dengan satu asrama tidak perlu memilih.
        $targets = $this->targets();
        if (count($targets) === 1) {
            $this->target = (string) array_key_first($targets);
        }
    }

    public function updatedDormKind(): void
    {
        if ($this->dorm_title === '' || in_array($this->dorm_title, self::TITLES, true)) {
            $this->dorm_title = self::TITLES[$this->dorm_kind] ?? '';
        }
    }

    /**
     * Semua cara yang diizinkan pesantren boleh dipakai di satu sesi: manual, kartu, QR, wajah.
     *
     * @return list<string>
     */
    private function modes(): array
    {
        return array_keys(AcademicSettings::modeOptions());
    }

    /**
     * Cara utama hanya menentukan status awal: bila ada cara pindai, semua belum hadir sampai tercatat.
     */
    private function primarySource(): string
    {
        foreach (['card', 'qr', 'face'] as $mode) {
            if (in_array($mode, $this->modes(), true)) {
                return $mode;
            }
        }

        return 'manual';
    }

    /**
     * @return array<string, string>
     */
    private function targets(): array
    {
        $dorms = $this->dormitories()->mapWithKeys(fn (Dormitory $dorm) => ['d:'.$dorm->id => 'Asrama '.$dorm->name])->all();

        if (! auth()->user()->can('academic.manage')) {
            return $dorms;
        }

        $classes = Classroom::query()->ordered()->get()->mapWithKeys(fn (Classroom $room) => ['c:'.$room->id => $room->name])->all();

        return ['all' => 'Semua santri aktif'] + $classes + $dorms;
    }

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
            // Di kelas ustadz cukup menandai yang tidak hadir, jadi semua mulai hadir; pindai tetap boleh.
            $session = $action->fromSchedule($schedule, $this->today(), 'manual', auth()->id(), $this->modes());
        } catch (AcademicException $exception) {
            $this->dispatch('toast', message: $exception->getMessage(), tone: 'danger');

            return null;
        }

        return $this->redirect(self::sessionUrl($session), navigate: true);
    }

    /**
     * Sesi tanpa jadwal: pilih peserta dan jenis, jam dicatat otomatis karena tidak ada hitungan terlambat.
     */
    public function openGroup(OpenAttendanceSession $action)
    {
        $this->resetErrorBag();
        if (! array_key_exists($this->target, $this->targets())) {
            $this->addError('target', 'Pilih peserta absen.');

            return null;
        }
        $this->validate(['dorm_kind' => 'required|in:asrama,sholat,kegiatan', 'dorm_title' => 'required|string|max:100'], [], ['dorm_title' => 'nama sesi']);

        [$classroomId, $dormitoryId] = match (true) {
            str_starts_with($this->target, 'c:') => [(int) substr($this->target, 2), null],
            str_starts_with($this->target, 'd:') => [null, (int) substr($this->target, 2)],
            default => [null, null],
        };

        try {
            $session = $action->forGroup($classroomId, $dormitoryId, $this->dorm_kind, trim($this->dorm_title), $this->today(),
                now(config('sanad.display_timezone'))->format('H:i'), $this->primarySource(), auth()->id(), $this->modes());
        } catch (AcademicException $exception) {
            $this->addError('target', $exception->getMessage());

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
            'targets' => $this->targets(),
            'modes' => AcademicSettings::modeOptions(),
            'reviewCount' => app(ReviewAttendance::class)->queueFor($user)->count(),
            'reviewUrl' => route($role ? $role.'.attendance.review' : 'staff.attendance.review'),
            'hasTerm' => StudentSchedule::activeTerm() !== null,
        ])->layout($role ? 'components.layouts.portal' : 'components.layouts.staff', $role ? ['role' => $role, 'active' => 'attendance', 'title' => 'Absensi'] : ['active' => 'attendance']);
    }
}
