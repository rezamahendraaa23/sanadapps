<?php

namespace App\Modules\Tahfidz\Livewire;

use App\Modules\Core\Models\Student;
use App\Modules\Tahfidz\Models\MutabaahItem;
use App\Modules\Tahfidz\Models\MutabaahLog;
use App\Modules\Tahfidz\Models\TahfidzRecord;
use App\Modules\Tahfidz\Support\TahfidzProgress;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Tahfidz dan mutabaah (S-07). Santri mengisi amalan hari ini; wali hanya melihat.
 */
#[Title('Tahfidz')]
class StudentTahfidz extends Component
{
    #[Url(as: 'anak', except: '')]
    public string $childUuid = '';

    private function children()
    {
        $user = auth()->user();

        return $user->type === 'guardian' ? ($user->guardian?->students()->orderBy('name')->get() ?? collect()) : collect(array_filter([$user->student]));
    }

    private function child(): ?Student
    {
        $children = $this->children();

        return $children->firstWhere('uuid', $this->childUuid) ?? $children->first();
    }

    private function today(): string
    {
        return now(config('sanad.display_timezone'))->toDateString();
    }

    /**
     * Yang sudah diverifikasi musyrif tidak bisa diubah santri.
     */
    public function toggle(string $itemUuid): void
    {
        abort_unless(auth()->user()->type === 'student' && Str::isUuid($itemUuid), 403);
        $student = $this->child();
        abort_if($student === null, 404);
        $item = MutabaahItem::query()->where('is_active', true)->where('uuid', $itemUuid)->firstOrFail();

        $log = MutabaahLog::query()->where('student_id', $student->id)->where('item_id', $item->id)->whereDate('log_date', $this->today())->first();

        if ($log?->verified_by !== null) {
            return;
        }

        if ($log === null) {
            MutabaahLog::create(['student_id' => $student->id, 'item_id' => $item->id, 'log_date' => $this->today(), 'status' => 'done']);

            return;
        }

        $log->update(['status' => $log->status === 'done' ? 'skipped' : 'done']);
    }

    public function render(): View
    {
        $user = auth()->user();
        $isGuardian = $user->type === 'guardian';
        $child = $this->child();
        $today = $this->today();

        return view('tahfidz.student', [
            'isGuardian' => $isGuardian,
            'children' => $this->children(),
            'child' => $child,
            'progress' => $child ? TahfidzProgress::forStudent($child) : null,
            'juzMap' => $child ? TahfidzProgress::juzMap($child) : collect(),
            'history' => $child ? TahfidzRecord::query()->with(['surahFrom', 'surahTo'])->where('student_id', $child->id)->latest('record_date')->latest('id')->limit(15)->get() : collect(),
            'items' => MutabaahItem::query()->where('is_active', true)->orderBy('name')->get(),
            'logs' => $child ? MutabaahLog::query()->where('student_id', $child->id)->whereDate('log_date', $today)->get()->keyBy('item_id') : collect(),
            'types' => TahfidzRecord::TYPES,
            'grades' => TahfidzRecord::GRADES,
        ])->layout('components.layouts.portal', $isGuardian ? ['role' => 'wali', 'active' => 'progress', 'title' => 'Tahfidz'] : ['role' => 'santri', 'active' => 'tahfidz', 'title' => 'Tahfidz']);
    }
}
