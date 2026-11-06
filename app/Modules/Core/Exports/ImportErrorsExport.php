<?php

namespace App\Modules\Core\Exports;

use App\Modules\Core\Models\Import;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

class ImportErrorsExport implements FromArray, WithHeadings
{
    public function __construct(private Import $import) {}

    public function array(): array
    {
        return array_map(fn (array $error) => [$error['row'], $error['name'], implode(' ', $error['messages'])], $this->import->errors);
    }

    /**
     * @return list<string>
     */
    public function headings(): array
    {
        return ['Baris', 'Nama', 'Kesalahan'];
    }
}
