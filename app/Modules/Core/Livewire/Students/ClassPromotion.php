<?php

namespace App\Modules\Core\Livewire\Students;

use App\Modules\Core\Actions\PromoteStudents;
use App\Modules\Core\Models\Classroom;
use App\Modules\Core\Models\StudentEnrollment;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use RuntimeException;

#[Layout('components.layouts.staff', ['active' => 'students'])]
#[Title('Kenaikan Kelas')]
class ClassPromotion extends Component
{
    private const GRADUATE = 'lulus';

    public string $from = '';

    public string $to = '';

    public function updated(): void
    {
        $this->resetErrorBag();
    }

    public function execute(PromoteStudents $action): void
    {
        $this->validate(['from' => 'required|exists:classrooms,id', 'to' => 'required'], ['from.required' => 'Pilih kelas asal.', 'to.required' => 'Pilih tujuan.'], ['from' => 'kelas asal']);

        if ($this->to !== self::GRADUATE && (! Classroom::query()->whereKey((int) $this->to)->exists() || $this->to === $this->from)) {
            $this->addError('to', 'Pilih kelas tujuan yang berbeda dari kelas asal.');

            return;
        }

        $count = $action->preview((int) $this->from)->count();
        $action->execute((int) $this->from, $this->to === self::GRADUATE ? null : (int) $this->to);

        $this->reset('from', 'to');
        $this->dispatch('close-modal', 'konfirmasi-kenaikan');
        $this->dispatch('toast', message: "{$count} santri diproses. Bisa dibatalkan dalam 24 jam.", tone: 'success');
    }

    public function undo(string $batch, PromoteStudents $action): void
    {
        try {
            $count = $action->undo($batch);
            $this->dispatch('toast', message: "{$count} santri dikembalikan ke kelas semula.", tone: 'success');
        } catch (RuntimeException $exception) {
            $this->dispatch('toast', message: $exception->getMessage(), tone: 'danger');
        }
    }

    public function render(PromoteStudents $action): View
    {
        $classrooms = Classroom::query()->orderBy('name')->pluck('name', 'id')->all();

        $batches = StudentEnrollment::query()
            ->select('batch_id', 'classroom_id', 'status', DB::raw('count(*) as total'), DB::raw('max(ended_at) as ended_at'))
            ->whereNotNull('batch_id')
            ->whereIn('status', ['promoted', 'graduated'])
            ->where('ended_at', '>', now()->subHours(PromoteStudents::UNDO_HOURS))
            ->groupBy('batch_id', 'classroom_id', 'status')
            ->orderByDesc('ended_at')
            ->get();

        return view('core.students.promotion', [
            'classrooms' => $classrooms,
            'targets' => [self::GRADUATE => 'Lulus', ...$classrooms],
            'preview' => $this->from !== '' ? $action->preview((int) $this->from) : null,
            'batches' => $batches,
            'targetName' => $this->to === self::GRADUATE ? 'Lulus' : ($classrooms[(int) $this->to] ?? null),
        ]);
    }
}
