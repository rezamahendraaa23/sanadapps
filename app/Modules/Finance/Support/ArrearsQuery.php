<?php

namespace App\Modules\Finance\Support;

use App\Modules\Finance\Models\Invoice;
use App\Support\SchoolDate;
use Illuminate\Support\Collection;

final class ArrearsQuery
{
    /**
     * Tunggakan per santri: invoice terlambat yang masih bersisa, void tidak ikut (PRD Sistem 8.4).
     *
     * @return Collection<int, array{student: mixed, classroom: ?string, invoices: int, outstanding: int, oldest_due: mixed, days: int}>
     */
    public static function rows(?int $classroomId = null): Collection
    {
        $today = SchoolDate::today();

        return Invoice::query()
            ->with('student.classroom')
            ->open()
            ->whereDate('due_date', '<', $today)
            ->whereNotNull('student_id')
            ->when($classroomId !== null, fn ($query) => $query->whereHas('student', fn ($student) => $student->where('classroom_id', $classroomId)))
            ->get()
            ->groupBy('student_id')
            ->map(function (Collection $invoices) use ($today): array {
                $oldest = $invoices->min(fn (Invoice $invoice) => $invoice->due_date);

                return [
                    'student' => $invoices->first()->student,
                    'classroom' => $invoices->first()->student->classroom?->name,
                    'invoices' => $invoices->count(),
                    'outstanding' => $invoices->sum(fn (Invoice $invoice) => $invoice->outstanding()),
                    'oldest_due' => $oldest,
                    'days' => (int) $oldest->diffInDays($today),
                ];
            })
            ->sortByDesc('outstanding')
            ->values();
    }
}
