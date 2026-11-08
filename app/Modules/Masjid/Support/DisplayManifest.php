<?php

namespace App\Modules\Masjid\Support;

use App\Modules\Affairs\Models\DutyAssignment;
use App\Modules\Core\Models\OrgChart;
use App\Modules\Core\Models\SchoolProfile;
use App\Modules\Core\Models\Student;
use App\Modules\Core\Support\ModuleRegistry;
use App\Modules\Masjid\Models\DisplayConsent;
use App\Modules\Masjid\Models\DisplayContent;
use App\Modules\Masjid\Models\DisplayScreen;
use App\Modules\Masjid\Models\IqamahSetting;
use App\Modules\Masjid\Models\KajianSchedule;
use App\Modules\Masjid\Models\KhutbahSchedule;
use App\Modules\Masjid\Models\MasjidProfile;
use App\Modules\Masjid\Models\PrayerTime;
use App\Modules\Masjid\Models\Quote;
use App\Support\SchoolDate;
use App\Support\SchoolIdentity;
use Carbon\CarbonImmutable;
use DateTimeZone;

/**
 * Semua yang dibutuhkan layar agar tetap berjalan tanpa server. Daftar kolom disusun tangan: yang tidak
 * tertulis di sini tidak pernah keluar, jadi data keuangan, kesehatan, dan pelanggaran tidak mungkin ikut.
 */
class DisplayManifest
{
    public function __construct(private ModuleRegistry $modules) {}

    /**
     * @return array<string, mixed>
     */
    public function build(DisplayScreen $screen): array
    {
        $profile = MasjidProfile::current();
        $school = SchoolProfile::current();
        $today = SchoolDate::today();
        $zone = new DateTimeZone($profile->timezone);

        $data = [
            'screen' => ['name' => $screen->name, 'profile' => $screen->profile, 'orientation' => $screen->orientation, 'pattern' => $screen->content_pattern, 'brightness' => $screen->brightness_schedule],
            'identity' => ['name' => $profile->name ?: $school->name, 'school' => $school->name, 'logo' => app(SchoolIdentity::class)->logoUrl()],
            // Pustaka terverifikasi ikut bergilir walau tidak dijadikan konten, agar layar tidak kosong.
            // Bagan struktur yang dipilih tampil di TV, paling dalam tiga tingkat agar terbaca dari jauh.
            'structures' => $this->structures(),
            'quotes' => Quote::query()->whereNotNull('verified_at')->where('is_active', true)->orderBy('id')->limit(60)->get()->map(fn (Quote $quote) => ['id' => 'q'.$quote->id, 'arabic' => $quote->text_ar, 'text' => $quote->text_id, 'source' => $quote->source_ref])->values()->all(),
            'tz_offset_minutes' => (int) ($zone->getOffset($today) / 60),
            'countdown_minutes' => $profile->countdown_minutes,
            'friday_mode' => $profile->friday_mode,
            'ramadan' => $profile->ramadan_start && $profile->ramadan_end ? ['start' => $profile->ramadan_start->toDateString(), 'end' => $profile->ramadan_end->toDateString()] : null,
            'names' => PrayerTime::NAMES,
            'prayers' => PrayerTime::query()->whereDate('date', '>=', $today->toDateString())->orderBy('date')->limit(60)->get()
                ->map(fn (PrayerTime $row) => ['date' => $row->date->toDateString(), 'hijri' => HijriDate::for($row->date->toDateString(), $profile)['label'], 'imsak' => $row->imsak, 'fajr' => $row->fajr, 'sunrise' => $row->sunrise, 'dhuha' => $row->dhuha, 'dhuhr' => $row->dhuhr, 'asr' => $row->asr, 'maghrib' => $row->maghrib, 'isha' => $row->isha])->values()->all(),
            'iqamah' => IqamahSetting::query()->get()->mapWithKeys(fn (IqamahSetting $row) => [$row->prayer => ['minutes' => $row->minutes_after_adhan, 'duration' => $row->prayer_duration_minutes, 'friday' => $row->friday_minutes, 'ramadan' => $row->ramadan_minutes]])->all(),
            'contents' => $this->contents($screen),
            'emergency' => $this->emergency($screen),
            'khutbah' => $this->khutbah($today),
            'kajian' => KajianSchedule::query()->where('is_active', true)->orderBy('day_of_week')->orderBy('start_time')->get()->map(fn (KajianSchedule $row) => ['title' => $row->title, 'speaker' => $row->speaker, 'day' => $row->day_of_week, 'time' => substr((string) $row->start_time, 0, 5), 'location' => $row->location])->values()->all(),
            'duties' => $this->duties($today),
        ];

        return ['version' => substr(hash('sha256', (string) json_encode($data)), 0, 16), 'server_time' => now()->getTimestampMs(), ...$data];
    }

    public function version(DisplayScreen $screen): string
    {
        return $this->build($screen)['version'];
    }

    private function targeted(DisplayScreen $screen)
    {
        return DisplayContent::query()->where('status', 'approved')
            ->where(fn ($query) => $query->whereNull('ends_at')->orWhere('ends_at', '>', now()))
            ->where(fn ($query) => $query->whereDoesntHave('screens')->orWhereHas('screens', fn ($inner) => $inner->whereKey($screen->id)));
    }

    /**
     * @return list<array{key: string, name: string, nodes: list<array<string, mixed>>}>
     */
    private function structures(): array
    {
        $map = function (array $nodes, int $depth) use (&$map): array {
            return $depth > 2 ? [] : array_map(fn (array $node) => [
                'title' => $node['position']->title,
                'name' => $node['position']->person_name,
                'initials' => $node['position']->initials(),
                'photo' => $node['position']->photo_path ? route('display.org-photo', $node['position']) : null,
                'children' => $map($node['children'], $depth + 1),
            ], $nodes);
        };

        return OrgChart::query()->where('show_on_tv', true)->orderBy('id')->get()
            ->map(fn (OrgChart $chart) => ['key' => $chart->key, 'name' => $chart->name, 'nodes' => $map($chart->tree(), 0)])
            ->filter(fn (array $chart) => $chart['nodes'] !== [])->values()->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function contents(DisplayScreen $screen): array
    {
        $consented = DisplayConsent::query()->active()->where('scope', 'name')->pluck('student_id')->all();
        $quotes = Quote::query()->whereNotNull('verified_at')->where('is_active', true)->get()->keyBy('id');

        return $this->targeted($screen)->whereNot('type', 'emergency')->orderByDesc('priority')->orderBy('id')->limit(200)->get()
            // Menyebut santri tanpa persetujuan wali aktif: tidak tayang (PRD Masjid 8.3).
            ->filter(fn (DisplayContent $content) => $content->student_id === null || in_array($content->student_id, $consented, true))
            ->map(function (DisplayContent $content) use ($quotes) {
                $body = $content->body;

                if ($content->type === 'quote') {
                    $quote = $quotes[$body['quote_id'] ?? 0] ?? null;

                    if ($quote === null) {
                        return null;
                    }

                    $body = ['arabic' => $quote->text_ar, 'text' => $quote->text_id, 'source' => $quote->source_ref];
                }

                return [
                    'id' => $content->uuid,
                    'type' => $content->type,
                    'layout' => $content->layout,
                    'title' => $content->title,
                    'body' => $body,
                    'media' => $content->media_path ? route('display.media', $content) : null,
                    'starts_at' => $content->starts_at?->getTimestampMs(),
                    'ends_at' => $content->ends_at?->getTimestampMs(),
                    'rule' => $content->schedule_rule,
                    'priority' => $content->priority,
                    'duration' => $content->duration_seconds,
                    'ticker' => $content->is_ticker,
                ];
            })->filter()->values()->all();
    }

    /**
     * @return array{id: string, text: string, ends_at: int}|null
     */
    private function emergency(DisplayScreen $screen): ?array
    {
        $content = $this->targeted($screen)->where('type', 'emergency')->latest('id')->first();

        return $content === null ? null : ['id' => $content->uuid, 'text' => (string) ($content->body['text'] ?? ''), 'ends_at' => $content->ends_at?->getTimestampMs()];
    }

    /**
     * @return list<array<string, string|null>>
     */
    private function khutbah(CarbonImmutable $today): array
    {
        $consented = DisplayConsent::query()->active()->where('scope', 'name')->pluck('student_id')->all();

        return KhutbahSchedule::query()->whereDate('date', '>=', $today->toDateString())->orderBy('date')->limit(8)->get()->map(function (KhutbahSchedule $row) use ($consented) {
            $imam = $row->imam_name;

            if ($row->imam_student_id !== null) {
                $imam = in_array($row->imam_student_id, $consented, true) ? Student::query()->whereKey($row->imam_student_id)->value('name') : null;
            }

            return ['date' => $row->date->toDateString(), 'imam' => $imam, 'khatib' => $row->khatib_name, 'theme' => $row->theme];
        })->all();
    }

    /**
     * Piket hari ini. Santri tanpa persetujuan wali tampil tanpa nama.
     *
     * @return list<array{name: string, person: string|null}>
     */
    private function duties(CarbonImmutable $today): array
    {
        if (! $this->modules->enabled('duty_roster')) {
            return [];
        }

        $consented = DisplayConsent::query()->active()->where('scope', 'name')->pluck('student_id')->all();

        return DutyAssignment::query()->with('roster')->whereDate('duty_date', $today->toDateString())->get()->map(fn (DutyAssignment $duty) => [
            'name' => $duty->roster->name,
            'person' => $duty->assignee_type === 'student' && ! in_array($duty->assignee_id, $consented, true) ? null : $duty->assigneeName(),
        ])->values()->all();
    }
}
