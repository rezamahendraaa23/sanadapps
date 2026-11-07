<?php

namespace App\Modules\Masjid\Http;

use App\Modules\Masjid\Models\DisplayContent;
use App\Modules\Masjid\Models\MasjidProfile;
use App\Modules\Masjid\Models\PrayerTime;
use App\Modules\Masjid\Support\HijriDate;
use App\Support\Pdf\PdfRenderer;
use App\Support\SchoolDate;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class PublicMasjidController
{
    public function today(): View
    {
        $date = SchoolDate::today()->toDateString();

        return view('masjid.public', [
            'profile' => MasjidProfile::current(),
            'today' => PrayerTime::query()->whereDate('date', $date)->first(),
            'hijri' => HijriDate::for($date),
            'contents' => DisplayContent::query()->where('status', 'approved')->where('is_public', true)->where('type', 'text')->where(fn ($query) => $query->whereNull('ends_at')->orWhere('ends_at', '>', now()))->latest('id')->limit(5)->get(),
        ]);
    }

    public function monthPdf(Request $request, PdfRenderer $pdf): Response
    {
        $month = preg_match('/^\d{4}-\d{2}$/', (string) $request->query('month')) ? (string) $request->query('month') : SchoolDate::today()->format('Y-m');

        return response($pdf->render(self::monthView($month)->render()), 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'inline; filename="jadwal-sholat-'.$month.'.pdf"']);
    }

    public static function monthView(string $month): View
    {
        $start = CarbonImmutable::parse($month.'-01');

        return view('masjid.pdf.month', [
            'profile' => MasjidProfile::current(),
            'start' => $start,
            'rows' => PrayerTime::query()->whereBetween('date', [$start->toDateString(), $start->endOfMonth()->toDateString()])->orderBy('date')->get(),
        ]);
    }
}
