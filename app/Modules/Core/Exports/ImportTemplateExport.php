<?php

namespace App\Modules\Core\Exports;

use App\Modules\Core\Support\StudentImportRow;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

class ImportTemplateExport implements FromArray, WithHeadings
{
    public function array(): array
    {
        return [];
    }

    /**
     * @return list<string>
     */
    public function headings(): array
    {
        return StudentImportRow::HEADINGS;
    }
}
