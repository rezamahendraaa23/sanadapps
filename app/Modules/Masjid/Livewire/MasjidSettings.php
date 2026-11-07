<?php

namespace App\Modules\Masjid\Livewire;

use App\Modules\Core\Actions\RecordAudit;
use App\Modules\Finance\Exports\ArrayExport;
use App\Modules\Masjid\Actions\ComputePrayerTimes;
use App\Modules\Masjid\Actions\ReferenceSchedule;
use App\Modules\Masjid\Actions\ResolveLocation;
use App\Modules\Masjid\Models\DisplayContent;
use App\Modules\Masjid\Models\DisplayScreen;
use App\Modules\Masjid\Models\HijriMonth;
use App\Modules\Masjid\Models\IqamahSetting;
use App\Modules\Masjid\Models\MasjidProfile;
use App\Modules\Masjid\Models\PrayerAdjustment;
use App\Modules\Masjid\Models\PrayerOverride;
use App\Modules\Masjid\Models\PrayerTime;
use App\Modules\Masjid\Support\HijriDate;
use App\Modules\Masjid\Support\MasjidException;
use App\Support\SchoolDate;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;
use Maatwebsite\Excel\Concerns\ToArray;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Facades\Excel;
use Throwable;

/**
 * Beranda masjid, lokasi dan metode, koreksi, iqamah, uji pembanding, jadwal bulanan (MS-01 sampai MS-05).
 */
#[Layout('components.layouts.staff', ['active' => 'masjid'])]
#[Title('Masjid')]
class MasjidSettings extends Component
{
    use WithFileUploads;

    #[Url(except: 'ringkasan')]
    public string $tab = 'ringkasan';

    public string $name = '';

    public string $city = '';

    public string $query = '';

    public string $latitude = '';

    public string $longitude = '';

    public string $timezone = 'Asia/Jakarta';

    #[Locked]
    public ?string $foundLabel = null;

    public string $fajr_angle = '20';

    public string $isha_angle = '18';

    public string $asr_method = 'standard';

    public string $imsak_minutes = '10';

    public string $schedule_source = 'computed';

    public bool $friday_mode = true;

    public bool $auto_attendance = false;

    public string $countdown_minutes = '15';

    public string $ramadan_start = '';

    public string $ramadan_end = '';

    public string $hijri_offset_days = '0';

    /** @var array<string, array<string, string>> */
    public array $iqamah = [];

    public string $adj_prayer = 'fajr';

    public string $adj_minutes = '';

    public string $adj_from = '';

    public string $ovr_date = '';

    public string $ovr_prayer = 'dhuhr';

    public string $ovr_time = '';

    public string $ovr_reason = '';

    public string $month = '';

    public $referenceFile = null;

    public string $hijri_year = '';

    public string $hijri_month = '9';

    public string $hijri_start = '';

    public function mount(): void
    {
        $profile = MasjidProfile::current();
        $this->fill([
            'name' => (string) $profile->name,
            'city' => (string) $profile->city,
            'query' => (string) $profile->address_query,
            'latitude' => $profile->latitude === null ? '' : (string) $profile->latitude,
            'longitude' => $profile->longitude === null ? '' : (string) $profile->longitude,
            'timezone' => $profile->timezone,
            'fajr_angle' => (string) $profile->fajr_angle,
            'isha_angle' => (string) $profile->isha_angle,
            'asr_method' => $profile->asr_method,
            'imsak_minutes' => (string) $profile->imsak_minutes,
            'schedule_source' => $profile->schedule_source,
            'friday_mode' => $profile->friday_mode,
            'auto_attendance' => $profile->auto_attendance,
            'countdown_minutes' => (string) $profile->countdown_minutes,
            'ramadan_start' => (string) $profile->ramadan_start?->toDateString(),
            'ramadan_end' => (string) $profile->ramadan_end?->toDateString(),
            'hijri_offset_days' => (string) $profile->hijri_offset_days,
            'month' => SchoolDate::today()->format('Y-m'),
            'adj_from' => SchoolDate::today()->toDateString(),
            'ovr_date' => SchoolDate::today()->toDateString(),
        ]);

        foreach (IqamahSetting::query()->get() as $row) {
            $this->iqamah[$row->prayer] = ['minutes' => (string) $row->minutes_after_adhan, 'duration' => (string) $row->prayer_duration_minutes, 'friday' => (string) $row->friday_minutes, 'ramadan' => (string) $row->ramadan_minutes];
        }
    }

    private function settings(): void
    {
        abort_unless(auth()->user()->can('masjid.settings'), 403);
    }

    public function findLocation(ResolveLocation $resolver): void
    {
        $this->settings();
        $this->resetErrorBag();

        if (mb_strlen(trim($this->query)) < 3) {
            $this->addError('query', 'Ketik nama kota atau alamat masjid.');

            return;
        }

        try {
            $found = $resolver->handle(trim($this->query));
        } catch (MasjidException $exception) {
            $this->addError('query', $exception->getMessage());

            return;
        }

        $this->latitude = (string) $found['latitude'];
        $this->longitude = (string) $found['longitude'];
        $this->timezone = $found['timezone'];
        $this->foundLabel = $found['label'];
    }

    public function saveProfile(ComputePrayerTimes $computer): void
    {
        $this->settings();
        $this->resetErrorBag();
        $this->validate([
            'name' => 'required|string|max:150',
            'city' => 'required|string|max:100',
            'latitude' => 'required|numeric|between:-11,6',
            'longitude' => 'required|numeric|between:94,142',
            'timezone' => 'required|in:Asia/Jakarta,Asia/Makassar,Asia/Jayapura',
            'fajr_angle' => 'required|numeric|between:10,25',
            'isha_angle' => 'required|numeric|between:10,25',
            'asr_method' => 'required|in:standard,hanafi',
            'imsak_minutes' => 'required|integer|between:0,30',
            'schedule_source' => 'required|in:computed,api',
            'countdown_minutes' => 'required|integer|between:1,60',
            'hijri_offset_days' => 'required|integer|between:-2,2',
            'ramadan_start' => 'nullable|date',
            'ramadan_end' => 'nullable|date|after_or_equal:ramadan_start|required_with:ramadan_start',
        ], ['latitude.between' => 'Lintang di luar wilayah Indonesia.', 'longitude.between' => 'Bujur di luar wilayah Indonesia.'], ['name' => 'nama masjid', 'city' => 'kota', 'latitude' => 'lintang', 'longitude' => 'bujur', 'ramadan_end' => 'akhir Ramadan']);

        $profile = MasjidProfile::current();
        $locationChanged = (string) $profile->latitude !== $this->latitude || (string) $profile->longitude !== $this->longitude;
        $profile->update([
            'name' => $this->name,
            'city' => $this->city,
            'address_query' => $this->query ?: null,
            'latitude' => (float) $this->latitude,
            'longitude' => (float) $this->longitude,
            'timezone' => $this->timezone,
            'geocode_source' => $locationChanged ? ($this->foundLabel ? 'OpenStreetMap Nominatim' : 'Manual') : $profile->geocode_source,
            'geocode_at' => $locationChanged ? now() : $profile->geocode_at,
            'api_location_ref' => $profile->city === $this->city ? $profile->api_location_ref : null,
            'fajr_angle' => (float) $this->fajr_angle,
            'isha_angle' => (float) $this->isha_angle,
            'asr_method' => $this->asr_method,
            'imsak_minutes' => (int) $this->imsak_minutes,
            'schedule_source' => $this->schedule_source,
            'friday_mode' => $this->friday_mode,
            'auto_attendance' => $this->auto_attendance,
            'countdown_minutes' => (int) $this->countdown_minutes,
            'hijri_offset_days' => (int) $this->hijri_offset_days,
            'ramadan_start' => $this->ramadan_start ?: null,
            'ramadan_end' => $this->ramadan_end ?: null,
        ]);

        $computer->handle();
        $this->dispatch('toast', message: 'Tersimpan. Jadwal 60 hari dihitung ulang.', tone: 'success');
    }

    public function saveIqamah(ComputePrayerTimes $computer): void
    {
        abort_unless(auth()->user()->canAny(['masjid.settings', 'masjid.schedule']), 403);
        $this->resetErrorBag();
        $this->validate(['iqamah.*.minutes' => 'required|integer|between:0,60', 'iqamah.*.duration' => 'required|integer|between:1,60', 'iqamah.*.friday' => 'nullable|integer|between:0,90', 'iqamah.*.ramadan' => 'nullable|integer|between:0,60'], ['iqamah.*.*' => 'Isi menit 0 sampai 60.']);

        foreach ($this->iqamah as $prayer => $row) {
            IqamahSetting::query()->where('prayer', $prayer)->update(['minutes_after_adhan' => (int) $row['minutes'], 'prayer_duration_minutes' => (int) $row['duration'], 'friday_minutes' => $row['friday'] === '' ? null : (int) $row['friday'], 'ramadan_minutes' => $row['ramadan'] === '' ? null : (int) $row['ramadan']]);
        }

        $this->dispatch('toast', message: 'Iqamah tersimpan.', tone: 'success');
    }

    public function addAdjustment(ComputePrayerTimes $computer): void
    {
        $this->settings();
        $this->resetErrorBag();
        $this->validate(['adj_prayer' => 'required|in:imsak,fajr,sunrise,dhuha,dhuhr,asr,maghrib,isha', 'adj_minutes' => 'required|integer|between:-15,15', 'adj_from' => 'required|date'], [], ['adj_minutes' => 'koreksi', 'adj_from' => 'berlaku mulai']);
        PrayerAdjustment::query()->updateOrCreate(['prayer' => $this->adj_prayer, 'effective_from' => $this->adj_from], ['minutes' => (int) $this->adj_minutes]);
        $computer->handle($this->adj_from);
        $this->reset('adj_minutes');
        $this->dispatch('toast', message: 'Koreksi tersimpan.', tone: 'success');
    }

    public function addOverride(ComputePrayerTimes $computer, RecordAudit $audit): void
    {
        abort_unless(auth()->user()->canAny(['masjid.settings', 'masjid.schedule']), 403);
        $this->resetErrorBag();
        $this->validate(['ovr_date' => 'required|date', 'ovr_prayer' => 'required|in:imsak,fajr,sunrise,dhuha,dhuhr,asr,maghrib,isha', 'ovr_time' => 'required|date_format:H:i', 'ovr_reason' => 'required|string|max:200'], [], ['ovr_date' => 'tanggal', 'ovr_time' => 'jam', 'ovr_reason' => 'alasan']);
        $override = PrayerOverride::query()->updateOrCreate(['date' => $this->ovr_date, 'prayer' => $this->ovr_prayer], ['time' => $this->ovr_time, 'reason' => $this->ovr_reason, 'created_by' => auth()->id()]);
        $audit->handle('masjid.override_set', 'prayer_override', $override->id, null, ['date' => $this->ovr_date, 'prayer' => $this->ovr_prayer, 'time' => $this->ovr_time]);
        $computer->handle($this->ovr_date, 1);
        $this->reset('ovr_time', 'ovr_reason');
        $this->dispatch('toast', message: 'Jadwal tanggal itu diubah manual.', tone: 'success');
    }

    public function removeOverride(string $uuid, ComputePrayerTimes $computer): void
    {
        abort_unless(auth()->user()->canAny(['masjid.settings', 'masjid.schedule']), 403);
        $override = PrayerOverride::query()->where('uuid', $uuid)->firstOrFail();
        $date = $override->date->toDateString();
        $override->delete();
        $computer->handle($date, 1);
    }

    public function fetchReference(ReferenceSchedule $reference): void
    {
        $this->settings();
        $this->resetErrorBag();

        try {
            $count = $reference->fetchMonth($this->month);
        } catch (MasjidException $exception) {
            $this->addError('month', $exception->getMessage());

            return;
        }

        $this->dispatch('toast', message: "{$count} hari jadwal resmi diambil.", tone: 'success');
    }

    public function importReference(ReferenceSchedule $reference): void
    {
        $this->settings();
        $this->resetErrorBag();
        $this->validate(['referenceFile' => 'required|file|mimes:xlsx,csv,txt|max:2048'], ['referenceFile.required' => 'Pilih berkas dulu.']);

        try {
            $rows = Excel::toArray(new class implements ToArray, WithHeadingRow
            {
                public function array(array $array): void {}
            }, $this->referenceFile)[0] ?? [];
        } catch (Throwable $exception) {
            report($exception);
            $this->addError('referenceFile', 'Berkas tidak bisa dibaca.');

            return;
        }

        $count = $reference->import($rows);
        $this->reset('referenceFile');
        $this->dispatch('toast', message: "{$count} baris jadwal pembanding masuk.", tone: $count ? 'success' : 'warning');
    }

    public function setHijriMonth(): void
    {
        $this->settings();
        $this->resetErrorBag();
        $this->validate(['hijri_year' => 'required|integer|between:1440,1500', 'hijri_month' => 'required|integer|between:1,12', 'hijri_start' => 'required|date'], [], ['hijri_year' => 'tahun hijriah', 'hijri_start' => 'tanggal awal']);
        HijriMonth::query()->updateOrCreate(['hijri_year' => (int) $this->hijri_year, 'hijri_month' => (int) $this->hijri_month], ['starts_on' => $this->hijri_start, 'set_by' => auth()->id(), 'note' => 'Ditetapkan admin']);
        $this->dispatch('toast', message: 'Awal bulan hijriah tersimpan.', tone: 'success');
    }

    public function exportMonth()
    {
        $start = CarbonImmutable::parse($this->month.'-01');
        $rows = PrayerTime::query()->whereBetween('date', [$start->toDateString(), $start->endOfMonth()->toDateString()])->orderBy('date')->get()
            ->map(fn (PrayerTime $row) => [$row->date->toDateString(), ...array_map(fn ($key) => $row->{$key}, array_keys(PrayerTime::NAMES))])->all();

        return Excel::download(new ArrayExport(['Tanggal', ...array_values(PrayerTime::NAMES)], $rows), 'jadwal-sholat-'.$this->month.'.xlsx');
    }

    public function render(ReferenceSchedule $reference): View
    {
        $profile = MasjidProfile::current();
        $today = SchoolDate::today();
        $todayRow = PrayerTime::query()->whereDate('date', $today->toDateString())->first();
        $start = preg_match('/^\d{4}-\d{2}$/', $this->month) ? CarbonImmutable::parse($this->month.'-01') : $today->startOfMonth();

        return view('masjid.settings', [
            'profile' => $profile,
            'todayRow' => $todayRow,
            'hijri' => HijriDate::for($today->toDateString(), $profile),
            'screens' => DisplayScreen::query()->where('is_active', true)->get(),
            'pending' => DisplayContent::query()->where('status', 'pending')->count(),
            'live' => DisplayContent::query()->where('status', 'approved')->where(fn ($query) => $query->whereNull('ends_at')->orWhere('ends_at', '>', now()))->count(),
            'adjustments' => PrayerAdjustment::query()->orderBy('prayer')->orderByDesc('effective_from')->get(),
            'overrides' => PrayerOverride::query()->whereDate('date', '>=', $today->toDateString())->orderBy('date')->get(),
            'comparison' => $this->tab === 'pembanding' ? $reference->compare($start->format('Y-m')) : null,
            'monthRows' => $this->tab === 'bulanan' ? PrayerTime::query()->whereBetween('date', [$start->toDateString(), $start->endOfMonth()->toDateString()])->orderBy('date')->get() : collect(),
            'names' => PrayerTime::NAMES,
            'canSettings' => auth()->user()->can('masjid.settings'),
        ]);
    }
}
