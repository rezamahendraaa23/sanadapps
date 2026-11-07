<?php

namespace App\Modules\Academic\Livewire;

use App\Modules\Academic\Support\AttendanceStats;
use App\Modules\Core\Models\Classroom;
use App\Support\SchoolDate;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('components.layouts.staff', ['active' => 'attendance'])]
#[Title('Rekap Absensi')]
class AttendanceRecap extends Component
{
    #[Url(as: 'kelas', except: '')]
    public string $classroom = '';

    #[Url(as: 'dari')]
    public string $from = '';

    #[Url(as: 'sampai')]
    public string $to = '';

    public function mount(): void
    {
        $today = now(config('sanad.display_timezone'));
        $this->from = self::date($this->from) ?? $today->copy()->startOfMonth()->toDateString();
        $this->to = self::date($this->to) ?? $today->toDateString();
    }

    public static function date(?string $value): ?string
    {
        return is_string($value) && preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $m) && checkdate((int) $m[2], (int) $m[3], (int) $m[1]) ? $value : null;
    }

    public function render(): View
    {
        $from = self::date($this->from) ?? SchoolDate::today()->toDateString();
        $to = self::date($this->to) ?? $from;
        $rows = AttendanceStats::perStudent($this->classroom !== '' ? (int) $this->classroom : null, null, $from, $to);

        return view('academic.attendance-recap', [
            'rows' => $rows,
            'classrooms' => Classroom::query()->orderBy('name')->pluck('name', 'id')->all(),
            'average' => $rows->isEmpty() ? null : (int) round($rows->avg('percent')),
            'params' => array_filter(['kelas' => $this->classroom, 'dari' => $from, 'sampai' => $to]),
        ]);
    }
}
