<?php

namespace App\Modules\Masjid\Http;

use App\Modules\Masjid\Actions\ComputePrayerTimes;
use App\Modules\Masjid\Models\DisplayContent;
use App\Modules\Masjid\Models\MasjidProfile;
use App\Modules\Masjid\Models\PrayerTime;
use App\Modules\Masjid\Support\HijriDate;
use App\Support\Pdf\PdfRenderer;
use App\Support\SchoolDate;
use App\Support\SchoolIdentity;
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
        $profile = MasjidProfile::current();
        $stored = PrayerTime::query()->whereBetween('date', [$start->toDateString(), $start->endOfMonth()->toDateString()])->orderBy('date')->get()->keyBy(fn (PrayerTime $row) => $row->date->toDateString());
        // Jadwal tersimpan hanya dari hari ini ke depan; hari lain di bulan yang sama dihitung saat dicetak tanpa disimpan, jadi PDF selalu sebulan penuh.
        $computer = app(ComputePrayerTimes::class);
        $rows = collect(range(0, $start->daysInMonth - 1))
            ->map(function (int $offset) use ($start, $stored, $computer, $profile) {
                $date = $start->addDays($offset)->toDateString();

                return $stored[$date] ?? ($profile->hasLocation() ? new PrayerTime(['date' => $date, ...$computer->forDate($date, $profile)]) : null);
            })
            ->filter()->values();
        // Tanggal hijriah per baris, dan rentang bulan hijriah untuk judul.
        $hijri = $rows->mapWithKeys(fn (PrayerTime $row) => [$row->date->toDateString() => HijriDate::for($row->date->toDateString(), $profile)]);
        $months = $hijri->map(fn (array $day) => HijriDate::MONTHS[$day['month'] - 1].' '.$day['year'])->unique()->values();

        return view('masjid.pdf.month', [
            'profile' => $profile,
            'school' => app(SchoolIdentity::class)->displayName(),
            'start' => $start,
            'rows' => $rows,
            'hijri' => $hijri,
            'hijriRange' => $months->isEmpty() ? null : $months->implode(' sampai ').' H',
        ]);
    }
}
