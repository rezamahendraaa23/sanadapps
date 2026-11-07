<?php

namespace App\Modules\Finance\Actions;

use App\Modules\Core\Models\Student;
use App\Modules\Finance\Events\InvoiceIssued;
use App\Modules\Finance\Models\FeeType;
use App\Modules\Finance\Models\Invoice;
use App\Modules\Finance\Models\InvoiceItem;
use App\Modules\Finance\Support\DocumentNumber;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class GenerateInvoices
{
    public function __construct(private CalculateCharges $charges) {}

    /**
     * @param  list<int>|null  $feeTypeIds  null berarti semua jenis bulanan yang aktif.
     * @return array{students: int, invoices: int, total: int, skipped: list<array{student: string, nis: string, fee: string, reason: string}>}
     */
    public function preview(CarbonImmutable $period, ?array $feeTypeIds = null, ?int $classroomId = null): array
    {
        $summary = ['students' => 0, 'invoices' => 0, 'total' => 0, 'skipped' => []];
        $feeTypes = $this->feeTypes($feeTypeIds);

        foreach ($this->students($classroomId) as $student) {
            $summary['students']++;
            $result = $this->charges->handle($student, $feeTypes, $period);
            $this->collectSkipped($summary, $student, $result['skipped']);

            if ($result['items'] !== []) {
                $summary['invoices']++;
                $summary['total'] += array_sum(array_column($result['items'], 'amount'));
            }
        }

        return $summary;
    }

    /**
     * Idempoten: item yang sudah ada dilewati, dan bentrok dari proses bersamaan ditangkap
     * oleh indeks unik di database, bukan dianggap kesalahan.
     *
     * @param  list<int>|null  $feeTypeIds
     * @return array{students: int, invoices: int, total: int, skipped: list<array{student: string, nis: string, fee: string, reason: string}>}
     */
    public function run(CarbonImmutable $period, ?array $feeTypeIds = null, ?int $classroomId = null, ?int $userId = null): array
    {
        $summary = ['students' => 0, 'invoices' => 0, 'total' => 0, 'skipped' => []];
        $feeTypes = $this->feeTypes($feeTypeIds);

        foreach ($this->students($classroomId) as $student) {
            $summary['students']++;
            $result = $this->charges->handle($student, $feeTypes, $period);
            $this->collectSkipped($summary, $student, $result['skipped']);

            if ($result['items'] === []) {
                continue;
            }

            try {
                DB::transaction(fn () => $this->store($student, $result['items'], $period, $userId));
                $summary['invoices']++;
                $summary['total'] += array_sum(array_column($result['items'], 'amount'));
            } catch (UniqueConstraintViolationException) {
                $this->collectSkipped($summary, $student, [['fee' => 'Semua', 'reason' => 'Sudah ditagih']]);
            }
        }

        return $summary;
    }

    /**
     * @param  list<array<string, mixed>>  $items
     */
    private function store(Student $student, array $items, CarbonImmutable $period, ?int $userId): void
    {
        // Satu invoice per santri per periode: jenis baru masuk ke invoice terbuka yang sudah ada.
        $invoice = Invoice::query()
            ->where('student_id', $student->id)
            ->where('source', 'auto')
            ->whereDate('period', $period)
            ->open()
            ->lockForUpdate()
            ->first();

        $invoice ??= new Invoice([
            'invoice_no' => DocumentNumber::next('INV'),
            'student_id' => $student->id,
            'period' => $period->toDateString(),
            'due_date' => min(array_column($items, 'due_date')),
            'source' => 'auto',
            'created_by' => $userId,
        ]);

        if (! $invoice->exists) {
            $invoice->save();
        }

        foreach ($items as $item) {
            unset($item['due_date']);
            InvoiceItem::create([...$item, 'invoice_id' => $invoice->id, 'student_id' => $student->id]);
        }

        $invoice->refreshTotals();
        $invoice->save();

        InvoiceIssued::dispatch($invoice);
    }

    /**
     * @param  list<int>|null  $feeTypeIds
     * @return Collection<int, FeeType>
     */
    private function feeTypes(?array $feeTypeIds): Collection
    {
        return FeeType::query()
            ->where('is_active', true)
            ->when($feeTypeIds === null, fn ($query) => $query->where('category', 'monthly'))
            ->when($feeTypeIds !== null, fn ($query) => $query->whereIn('id', $feeTypeIds))
            ->orderBy('name')
            ->get();
    }

    /**
     * @return Collection<int, Student>
     */
    private function students(?int $classroomId): Collection
    {
        return Student::query()
            ->with('classroom')
            ->where('status', 'active')
            ->when($classroomId !== null, fn ($query) => $query->where('classroom_id', $classroomId))
            ->orderBy('name')
            ->get();
    }

    /**
     * @param  array<string, mixed>  $summary
     * @param  list<array{fee: string, reason: string}>  $skipped
     */
    private function collectSkipped(array &$summary, Student $student, array $skipped): void
    {
        foreach ($skipped as $entry) {
            $summary['skipped'][] = ['student' => $student->name, 'nis' => $student->nis, ...$entry];
        }
    }
}
