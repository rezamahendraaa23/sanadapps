<?php

namespace App\Modules\Finance\Actions;

use App\Modules\Core\Models\Student;
use App\Modules\Finance\Models\FeeType;
use App\Modules\Finance\Models\InvoiceItem;
use App\Modules\Finance\Models\StudentDiscount;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class CalculateCharges
{
    public function __construct(private ResolveFeeRate $rates) {}

    /**
     * Rincian tagihan satu santri untuk satu periode, tanpa menyimpan apa pun. Dipakai bersama
     * oleh pratinjau dan eksekusi agar hasil keduanya pasti sama.
     *
     * @param  Collection<int, FeeType>  $feeTypes
     * @return array{items: list<array<string, mixed>>, skipped: list<array{fee: string, reason: string}>}
     */
    public function handle(Student $student, Collection $feeTypes, CarbonInterface $period): array
    {
        $items = [];
        $skipped = [];

        foreach ($feeTypes as $feeType) {
            $periodKey = self::periodKey($feeType, $period);

            if (InvoiceItem::query()->where('student_id', $student->id)->where('fee_type_id', $feeType->id)->where('period_key', $periodKey)->exists()) {
                $skipped[] = ['fee' => $feeType->name, 'reason' => 'Sudah ditagih'];

                continue;
            }

            $assignment = $this->rates->assignmentOn($student, $feeType, $period);
            $participates = $feeType->is_mandatory ? ($assignment === null || $assignment->is_active) : ($assignment !== null && $assignment->is_active);

            if (! $participates) {
                continue;
            }

            $rate = $this->rates->handle($student, $feeType, $period);

            if ($rate === null) {
                $skipped[] = ['fee' => $feeType->name, 'reason' => 'Tarif belum diatur'];

                continue;
            }

            $discount = min($rate['amount'], $this->discountFor($student, $feeType, $rate['amount'], $period));

            $items[] = [
                'fee_type_id' => $feeType->id,
                'period_key' => $periodKey,
                'description' => self::description($feeType, $period),
                'qty' => 1,
                'unit_price' => $rate['amount'],
                'discount' => $discount,
                'amount' => $rate['amount'] - $discount,
                'due_date' => self::dueDate($feeType, $period),
            ];
        }

        return ['items' => $items, 'skipped' => $skipped];
    }

    private function discountFor(Student $student, FeeType $feeType, int $amount, CarbonInterface $period): int
    {
        return StudentDiscount::query()
            ->where('student_id', $student->id)
            ->where(fn ($query) => $query->whereNull('fee_type_id')->orWhere('fee_type_id', $feeType->id))
            ->whereDate('valid_from', '<=', $period)
            ->where(fn ($query) => $query->whereNull('valid_to')->orWhereDate('valid_to', '>=', $period))
            ->get()
            ->sum(fn (StudentDiscount $discount) => $discount->type === 'percent'
                ? intdiv($amount * $discount->value, 100)
                : $discount->value);
    }

    public static function periodKey(FeeType $feeType, CarbonInterface $period): string
    {
        return match ($feeType->category) {
            'yearly' => $period->format('Y'),
            'one_time' => 'sekali',
            default => $period->format('Y-m'),
        };
    }

    public static function description(FeeType $feeType, CarbonInterface $period): string
    {
        return match ($feeType->category) {
            'yearly' => $feeType->name.' '.$period->format('Y'),
            'one_time' => $feeType->name,
            default => $feeType->name.' '.$period->translatedFormat('F Y'),
        };
    }

    public static function dueDate(FeeType $feeType, CarbonInterface $period): string
    {
        $day = min($feeType->due_day ?? 10, $period->daysInMonth);

        return $period->copy()->setDay($day)->toDateString();
    }
}
