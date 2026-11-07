<?php

namespace App\Modules\Finance\Exports;

use App\Modules\Finance\Models\Invoice;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class InvoicesExport implements FromQuery, WithHeadings, WithMapping
{
    /**
     * @param  array<string, string|null>  $filters
     */
    public function __construct(private array $filters = []) {}

    public function query(): Builder
    {
        return Invoice::query()
            ->with('student:id,name,nis')
            ->when($this->filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($this->filters['kelas'] ?? null, fn (Builder $query, string $classroom) => $query->whereHas('student', fn (Builder $student) => $student->where('classroom_id', (int) $classroom)))
            ->when($this->filters['periode'] ?? null, fn (Builder $query, string $period) => $query->whereDate('period', $period.'-01'))
            ->latest('id');
    }

    /**
     * @return list<string>
     */
    public function headings(): array
    {
        return ['Nomor', 'NIS', 'Santri', 'Periode', 'Jatuh tempo', 'Total', 'Terbayar', 'Sisa', 'Status'];
    }

    /**
     * @param  Invoice  $invoice
     * @return list<mixed>
     */
    public function map($invoice): array
    {
        return [$invoice->invoice_no, $invoice->student?->nis, $invoice->student?->name, $invoice->period?->format('Y-m'), $invoice->due_date->toDateString(), $invoice->total, $invoice->paid_total, $invoice->outstanding(), $invoice->status];
    }
}
