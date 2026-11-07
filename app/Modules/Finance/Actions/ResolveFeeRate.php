<?php

namespace App\Modules\Finance\Actions;

use App\Modules\Core\Models\Student;
use App\Modules\Finance\Models\FeeRate;
use App\Modules\Finance\Models\FeeType;
use App\Modules\Finance\Models\StudentFeeAssignment;
use Carbon\CarbonInterface;

class ResolveFeeRate
{
    /**
     * Urutan prioritas PRD Sistem 8.1: penugasan, santri, kelas, asrama, jenjang, default.
     *
     * @return array{amount: int, source: string}|null null bila tarif belum diatur.
     */
    public function handle(Student $student, FeeType $feeType, CarbonInterface $on): ?array
    {
        $assignment = $this->assignmentOn($student, $feeType, $on);

        if ($assignment?->custom_amount !== null) {
            return ['amount' => $assignment->custom_amount, 'source' => 'Penugasan santri'];
        }

        $rates = FeeRate::query()
            ->where('fee_type_id', $feeType->id)
            ->whereDate('valid_from', '<=', $on)
            ->where(fn ($query) => $query->whereNull('valid_to')->orWhereDate('valid_to', '>=', $on))
            ->latest('valid_from')
            ->get();

        $level = $student->classroom?->level;

        $candidates = [
            'Tarif santri' => $rates->first(fn (FeeRate $rate) => $rate->scope === 'student' && $rate->scope_id === $student->id),
            'Tarif kelas' => $rates->first(fn (FeeRate $rate) => $rate->scope === 'classroom' && $student->classroom_id !== null && $rate->scope_id === $student->classroom_id),
            'Tarif asrama' => $rates->first(fn (FeeRate $rate) => $rate->scope === 'dormitory' && $student->dormitory_id !== null && $rate->scope_id === $student->dormitory_id),
            'Tarif jenjang' => $rates->first(fn (FeeRate $rate) => $rate->scope === 'level' && $level !== null && $rate->scope_level === $level),
            'Tarif umum' => $rates->first(fn (FeeRate $rate) => $rate->scope === 'default'),
        ];

        foreach ($candidates as $source => $rate) {
            if ($rate !== null) {
                return ['amount' => $rate->amount, 'source' => $source];
            }
        }

        return null;
    }

    public function assignmentOn(Student $student, FeeType $feeType, CarbonInterface $on): ?StudentFeeAssignment
    {
        return StudentFeeAssignment::query()
            ->where('student_id', $student->id)
            ->where('fee_type_id', $feeType->id)
            ->whereDate('start_month', '<=', $on)
            ->where(fn ($query) => $query->whereNull('end_month')->orWhereDate('end_month', '>=', $on))
            ->latest('start_month')
            ->first();
    }
}
