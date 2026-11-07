<?php

namespace App\Support\Pdf;

interface PdfRenderer
{
    /**
     * @return string Isi berkas PDF.
     */
    public function render(string $html, string $format = 'A4'): string;
}
