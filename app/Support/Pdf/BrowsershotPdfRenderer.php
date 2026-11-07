<?php

namespace App\Support\Pdf;

use Spatie\Browsershot\Browsershot;

/**
 * Memakai Chromium karena teks Arab butuh shaping dan RTL yang benar (PRD Sistem 4.1).
 */
class BrowsershotPdfRenderer implements PdfRenderer
{
    public function render(string $html, string $format = 'A4'): string
    {
        $shot = Browsershot::html($html)
            ->format($format)
            ->showBackground()
            ->margins(0, 0, 0, 0)
            ->waitUntilNetworkIdle();

        if ($chrome = config('sanad.pdf.chrome_path')) {
            $shot->setChromePath($chrome);
        }

        if (config('sanad.pdf.no_sandbox')) {
            $shot->noSandbox();
        }

        return $shot->pdf();
    }
}
