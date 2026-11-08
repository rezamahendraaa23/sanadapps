<?php

use App\Modules\Academic\Models\AttendanceSession;
use App\Modules\Academic\Models\Schedule;
use App\Modules\Affairs\Models\DutyAssignment;
use App\Modules\Affairs\Models\DutyRoster;
use App\Modules\Core\Livewire\Announcements\AnnouncementIndex;
use App\Modules\Core\Models\AcademicYear;
use App\Modules\Core\Models\Announcement;
use App\Modules\Core\Models\Dormitory;
use App\Modules\Core\Models\Guardian;
use App\Modules\Core\Models\Module;
use App\Modules\Core\Models\Student;
use App\Modules\Core\Models\Term;
use App\Modules\Core\Models\User;
use App\Modules\Core\Support\ModuleRegistry;
use App\Modules\Masjid\Actions\ApplyPrayerToSanad;
use App\Modules\Masjid\Actions\ComputePrayerTimes;
use App\Modules\Masjid\Actions\ManageContent;
use App\Modules\Masjid\Actions\ManageScreens;
use App\Modules\Masjid\Actions\ReferenceSchedule;
use App\Modules\Masjid\Actions\ResolveLocation;
use App\Modules\Masjid\Livewire\DisplayContents;
use App\Modules\Masjid\Livewire\DisplayScreens;
use App\Modules\Masjid\Livewire\GuardianDisplayConsent;
use App\Modules\Masjid\Livewire\MasjidSettings;
use App\Modules\Masjid\Models\DisplayConsent;
use App\Modules\Masjid\Models\DisplayContent;
use App\Modules\Masjid\Models\KhutbahSchedule;
use App\Modules\Masjid\Models\MasjidProfile;
use App\Modules\Masjid\Models\PrayerAdjustment;
use App\Modules\Masjid\Models\PrayerTime;
use App\Modules\Masjid\Models\Quote;
use App\Modules\Masjid\Support\DisplayManifest;
use App\Modules\Masjid\Support\HijriDate;
use App\Modules\Masjid\Support\MasjidException;
use App\Modules\Masjid\Support\QuoteTemplates;
use App\Modules\Masjid\Support\YoutubeLink;
use App\Support\Pdf\PdfRenderer;
use Carbon\CarbonImmutable;
use Database\Seeders\ModuleSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    (new ModuleSeeder)->run();
    (new RoleSeeder)->run();
    Module::query()->whereIn('module_key', ['masjid_prayer', 'masjid_display', 'masjid_public', 'duty_roster', 'attendance'])->update(['is_enabled' => true]);
    app(ModuleRegistry::class)->forget();
    Http::preventStrayRequests();
    // Jumat 6 November 2026, 10.00 WIB.
    $this->travelTo('2026-11-06 03:00:00');

    $this->admin = tap(User::create(['name' => 'Admin', 'email' => 'a@uji.test', 'type' => 'staff']))->assignRole('admin');
    $this->takmir = tap(User::create(['name' => 'Takmir', 'email' => 't@uji.test', 'type' => 'staff']))->assignRole('masjid_manager');
    $this->humas = tap(User::create(['name' => 'Humas', 'email' => 'h@uji.test', 'type' => 'staff']))->assignRole('masjid_content');
    $this->ustadz = tap(User::create(['name' => 'Ustadz', 'email' => 'u@uji.test', 'type' => 'staff']))->assignRole('ustadz');

    MasjidProfile::current()->update(['name' => 'Masjid Uji', 'city' => 'Jakarta', 'latitude' => -6.1754, 'longitude' => 106.8272, 'timezone' => 'Asia/Jakarta']);
    app(ComputePrayerTimes::class)->handle();
});

function pairScreen(array $data = []): array
{
    $screens = app(ManageScreens::class);
    $pairing = $screens->startPairing();
    $screen = $screens->claim($pairing['code'], ['name' => 'TV Utama', 'profile' => 'masjid', ...$data]);

    return [$screen, $screens->pairingStatus($pairing['pairing_token'])['token']];
}

function content(array $values): DisplayContent
{
    return DisplayContent::create(['type' => 'text', 'title' => 'Info', 'body' => ['text' => 'Isi'], 'status' => 'approved', ...$values]);
}

describe('jadwal', function () {
    it('golden test: empat kota tiga zona sepanjang 2026 cocok dengan jadwal Kemenag paling banyak 1 menit', function () {
        $data = json_decode(file_get_contents(base_path('tests/Fixtures/prayer-reference-2026.json')), true);
        $fields = ['imsak' => 'imsak', 'fajr' => 'subuh', 'sunrise' => 'terbit', 'dhuha' => 'dhuha', 'dhuhr' => 'dzuhur', 'asr' => 'ashar', 'maghrib' => 'maghrib', 'isha' => 'isya'];
        $worst = 0;
        $checked = 0;

        foreach ($data['cities'] as $city) {
            $profile = new MasjidProfile(['latitude' => $city['lat'], 'longitude' => $city['lng'], 'timezone' => $city['tz'], 'fajr_angle' => 20, 'isha_angle' => 18, 'asr_method' => 'standard', 'imsak_minutes' => 10, 'dhuha_offset_minutes' => 28, 'schedule_source' => 'computed']);

            foreach ($city['rows'] as $row) {
                $ours = app(ComputePrayerTimes::class)->forDate($row['date'], $profile);

                foreach ($fields as $prayer => $field) {
                    [$h1, $m1] = explode(':', $ours[$prayer]);
                    [$h2, $m2] = explode(':', $row[$field]);
                    $worst = max($worst, abs(($h1 * 60 + $m1) - ($h2 * 60 + $m2)));
                    $checked++;
                }
            }
        }

        expect($checked)->toBeGreaterThan(2000)->and($worst)->toBeLessThanOrEqual(1);
    });

    it('60 hari dihitung; koreksi hanya mulai tanggal berlaku; timpa manual tercatat', function () {
        expect(PrayerTime::count())->toBe(60);
        $before = PrayerTime::query()->whereDate('date', '2026-11-10')->value('isha');

        PrayerAdjustment::create(['prayer' => 'isha', 'minutes' => 5, 'effective_from' => '2026-11-10']);
        app(ComputePrayerTimes::class)->handle('2026-11-10');
        $after = PrayerTime::query()->whereDate('date', '2026-11-10')->value('isha');
        expect(CarbonImmutable::parse('2026-01-01 '.$before)->diffInMinutes(CarbonImmutable::parse('2026-01-01 '.$after)))->toEqual(3.0);

        $this->actingAs($this->admin);
        Livewire::test(MasjidSettings::class)->set('tab', 'koreksi')->set('ovr_date', '2026-11-07')->set('ovr_prayer', 'dhuhr')->set('ovr_time', '12:00')->call('addOverride')->assertHasErrors('ovr_reason')
            ->set('ovr_reason', 'Keputusan takmir')->call('addOverride')->assertHasNoErrors();
        $row = PrayerTime::query()->whereDate('date', '2026-11-07')->first();
        expect($row->dhuhr)->toBe('12:00')->and($row->source)->toBe('override')
            ->and(DB::table('audit_logs')->where('action', 'masjid.override_set')->exists())->toBeTrue();
    });

    it('lokasi dicari lewat layanan peta, hanya nama tempat yang dikirim; gagal memberi pesan', function () {
        Http::fake(['nominatim.openstreetmap.org/*' => Http::response([['lat' => '-0.5022', 'lon' => '117.1536', 'display_name' => 'Samarinda, Kalimantan Timur']])]);
        $found = app(ResolveLocation::class)->handle('Samarinda');
        expect($found['timezone'])->toBe('Asia/Makassar')->and($found['latitude'])->toBe(-0.5022);
        Http::assertSent(fn ($request) => str_contains($request->url(), 'q=Samarinda') && ! str_contains($request->url(), '@'));

    });

    it('lokasi tidak ditemukan memberi pesan', function () {
        Http::fake(['nominatim.openstreetmap.org/*' => Http::response([])]);
        expect(fn () => app(ResolveLocation::class)->handle('Tidak Ada'))->toThrow(MasjidException::class, 'tidak ditemukan');
    });

    it('pembanding dari API: selisih ditandai; API mati tidak merusak jadwal', function () {
        Http::fake([
            'api.myquran.com/v2/sholat/kota/cari/*' => Http::response(['data' => [['id' => '1301', 'lokasi' => 'KOTA JAKARTA']]]),
            'api.myquran.com/v2/sholat/jadwal/*' => Http::response(['data' => ['jadwal' => [
                ['date' => '2026-11-06', 'imsak' => '03:57', 'subuh' => '04:07', 'terbit' => '05:23', 'dhuha' => '05:51', 'dzuhur' => '11:40', 'ashar' => '14:57', 'maghrib' => '17:50', 'isya' => '19:01'],
                ['date' => '2026-11-07', 'imsak' => '03:57', 'subuh' => '04:20', 'terbit' => '05:23', 'dhuha' => '05:51', 'dzuhur' => '11:40', 'ashar' => '14:57', 'maghrib' => '17:50', 'isya' => '19:01'],
            ]]]),
        ]);
        $reference = app(ReferenceSchedule::class);
        expect($reference->fetchMonth('2026-11'))->toBe(2)->and(MasjidProfile::current()->api_location_ref)->toBe('1301');

        $result = $reference->compare('2026-11');
        expect($result['compared'])->toBe(2)->and($result['rows'][1]['cells']['fajr']['bad'])->toBeTrue()->and($result['flagged'])->toBeGreaterThanOrEqual(1);

    });

    it('API jadwal mati: pesan jelas, jadwal tersimpan tidak terganggu', function () {
        Http::fake(['api.myquran.com/*' => Http::response('rusak', 500)]);
        expect(fn () => app(ReferenceSchedule::class)->fetchMonth('2026-12'))->toThrow(MasjidException::class, 'hitungan sendiri');
        expect(PrayerTime::count())->toBe(60);
    });

    it('tanggal hijriah perkiraan, bisa ditetapkan manual', function () {
        $hijri = HijriDate::for('2026-11-06');
        expect($hijri['month'])->toBeIn([5, 6])->and($hijri['label'])->toContain('1448 H');

        DB::table('hijri_months')->insert(['hijri_year' => 1448, 'hijri_month' => 9, 'starts_on' => '2027-02-08', 'created_at' => now(), 'updated_at' => now()]);
        expect(HijriDate::for('2027-02-10'))->toMatchArray(['day' => 3, 'month' => 9, 'approximate' => false]);
    });

    it('jadwal santri berjangkar ikut bergeser; sesi absensi sholat dibuka saat iqamah', function () {
        $year = AcademicYear::create(['name' => '2026', 'start_date' => '2026-07-01', 'end_date' => '2027-06-30', 'is_active' => true]);
        $term = Term::create(['academic_year_id' => $year->id, 'name' => 'G', 'start_date' => '2026-07-01', 'end_date' => '2026-12-31', 'is_active' => true]);
        $schedule = Schedule::create(['term_id' => $term->id, 'day_of_week' => 5, 'start_time' => '12:00', 'end_time' => '12:30', 'activity_type' => 'sholat', 'title' => 'Dzuhur', 'anchor_prayer' => 'dhuhr', 'anchor_offset_minutes' => 5]);
        $dhuhr = PrayerTime::query()->whereDate('date', '2026-11-06')->value('dhuhr');

        expect(app(ApplyPrayerToSanad::class)->shiftAnchoredSchedules())->toBe(1);
        expect(substr($schedule->fresh()->start_time, 0, 5))->toBe(CarbonImmutable::parse('2026-01-01 '.$dhuhr)->addMinutes(5)->format('H:i'))
            ->and(substr($schedule->fresh()->end_time, 0, 5))->toBe(CarbonImmutable::parse('2026-01-01 '.$dhuhr)->addMinutes(35)->format('H:i'));

        $musyrif = tap(User::create(['name' => 'M', 'email' => 'm@uji.test', 'type' => 'staff']))->assignRole('musyrif');
        Dormitory::create(['name' => 'A', 'gender' => 'L', 'musyrif_user_id' => $musyrif->id]);
        MasjidProfile::current()->update(['auto_attendance' => true]);
        $this->travelTo(CarbonImmutable::parse('2026-11-06 '.$dhuhr, 'Asia/Jakarta')->addMinutes(11)->utc());

        expect(app(ApplyPrayerToSanad::class)->openAttendance())->toBe(1)->and(app(ApplyPrayerToSanad::class)->openAttendance())->toBe(0);
        expect(AttendanceSession::first()->kind)->toBe('sholat');
    });
});

describe('layar', function () {
    it('pairing: kode sekali pakai, token keluar sekali, kode salah ditolak, dicabut kembali ke pairing', function () {
        $screens = app(ManageScreens::class);
        $pairing = $screens->startPairing();
        expect($screens->pairingStatus($pairing['pairing_token']))->toBe(['status' => 'waiting']);
        expect(fn () => $screens->claim('000000', ['name' => 'X']))->toThrow(MasjidException::class);

        $screen = $screens->claim($pairing['code'], ['name' => 'TV']);
        $token = $screens->pairingStatus($pairing['pairing_token'])['token'];
        expect($screens->pairingStatus($pairing['pairing_token'])['status'])->toBe('expired')
            ->and(fn () => $screens->claim($pairing['code'], ['name' => 'Y']))->toThrow(MasjidException::class);

        $this->getJson('/api/v1/display/manifest', ['Authorization' => 'Bearer '.$token])->assertOk()->assertJsonPath('screen.name', 'TV');
        $screens->revoke($screen);
        $this->getJson('/api/v1/display/manifest', ['Authorization' => 'Bearer '.$token])->assertUnauthorized();
    });

    it('kode kedaluwarsa setelah 10 menit', function () {
        $pairing = app(ManageScreens::class)->startPairing();
        $this->travel(11)->minutes();
        expect(fn () => app(ManageScreens::class)->claim($pairing['code'], ['name' => 'X']))->toThrow(MasjidException::class)
            ->and(app(ManageScreens::class)->pairingStatus($pairing['pairing_token'])['status'])->toBe('expired');
    });

    it('manifest: hanya konten disetujui, dalam jendela, untuk layar ini; jadwal 60 hari; tanpa data sensitif', function () {
        [$screen, $token] = pairScreen();
        [$other] = pairScreen(['name' => 'TV Kantin']);
        content(['title' => 'Tayang']);
        content(['title' => 'Draf', 'status' => 'draft']);
        content(['title' => 'Menunggu', 'status' => 'pending']);
        content(['title' => 'Lewat', 'ends_at' => now()->subMinute()]);
        content(['title' => 'Kantin saja'])->screens()->sync([$other->id]);

        $manifest = $this->getJson('/api/v1/display/manifest', ['Authorization' => 'Bearer '.$token])->assertOk()->json();
        expect(array_column($manifest['contents'], 'title'))->toBe(['Tayang'])
            ->and($manifest['prayers'])->toHaveCount(60)
            ->and($manifest['server_time'])->toBeInt();

        $json = json_encode($manifest);
        foreach (['invoice', 'health', 'discipline', 'balance', 'nis', 'phone'] as $word) {
            expect($json)->not->toContain('"'.$word);
        }
    });

    it('nama santri hanya tampil dengan persetujuan wali; dicabut langsung hilang', function () {
        [$screen] = pairScreen();
        $student = Student::create(['nis' => 'S1', 'name' => 'Ahmad Rahasia', 'gender' => 'L']);
        content(['title' => 'Selamat untuk juara', 'student_id' => $student->id]);
        $roster = DutyRoster::create(['name' => 'Muadzin', 'day_of_week' => 5, 'start_time' => '11:00', 'end_time' => '12:00']);
        DutyAssignment::create(['roster_id' => $roster->id, 'assignee_type' => 'student', 'assignee_id' => $student->id, 'duty_date' => '2026-11-06']);
        KhutbahSchedule::create(['date' => '2026-11-13', 'khatib_name' => 'Ustadz Ali', 'imam_student_id' => $student->id]);

        $manifest = app(DisplayManifest::class)->build($screen);
        expect($manifest['contents'])->toBe([])->and(json_encode($manifest))->not->toContain('Ahmad Rahasia')->and($manifest['duties'][0]['person'])->toBeNull();

        $guardianUser = tap(User::create(['name' => 'Wali', 'phone' => '6281200000001', 'type' => 'guardian']))->assignRole('wali');
        Guardian::create(['user_id' => $guardianUser->id, 'name' => 'Wali', 'phone_wa' => '6281200000001'])->students()->attach($student->id, ['relation' => 'ayah', 'is_primary' => true, 'is_payer' => true]);
        $this->actingAs($guardianUser);
        Livewire::test(GuardianDisplayConsent::class)->assertSee('Ahmad Rahasia')->call('toggle', $student->uuid, 'name');

        $manifest = app(DisplayManifest::class)->build($screen);
        expect(json_encode($manifest))->toContain('Ahmad Rahasia')->and($manifest['khutbah'][0]['imam'])->toBe('Ahmad Rahasia');

        Livewire::test(GuardianDisplayConsent::class)->call('toggle', $student->uuid, 'name');
        expect(json_encode(app(DisplayManifest::class)->build($screen)))->not->toContain('Ahmad Rahasia')
            ->and(DisplayConsent::count())->toBe(1);
    });

    it('ayat hanya yang terverifikasi; versi berubah saat konten berubah', function () {
        [$screen] = pairScreen();
        $quote = Quote::create(['text_ar' => 'إِنَّ اللَّهَ مَعَ الصَّابِرِينَ', 'text_id' => 'Allah bersama orang sabar', 'source_ref' => 'QS. Al-Baqarah: 153']);
        content(['type' => 'quote', 'title' => 'Ayat', 'body' => ['quote_id' => $quote->id]]);
        $version = app(DisplayManifest::class)->version($screen);
        expect(app(DisplayManifest::class)->build($screen)['contents'])->toBe([]);

        $quote->update(['verified_at' => now(), 'verified_by' => $this->takmir->id]);
        $manifest = app(DisplayManifest::class)->build($screen);
        expect($manifest['contents'][0]['body']['source'])->toBe('QS. Al-Baqarah: 153')->and($manifest['version'])->not->toBe($version);
    });

    it('templat ayat dan hadis masuk pustaka belum terverifikasi, tanpa duplikat', function () {
        $this->actingAs($this->humas);
        $page = Livewire::test(DisplayContents::class)->set('tab', 'pustaka')->assertSee('Sholat berjamaah')->assertSee('HR. Bukhari no. 645 dan Muslim no. 650');
        $page->call('addTemplates', 'jamaah', 1);
        expect(Quote::where('source_ref', 'HR. Bukhari no. 645 dan Muslim no. 650')->firstOrFail()->verified_at)->toBeNull();
        $page->assertSee('Sudah di pustaka')->call('addTemplates', 'jamaah');
        expect(Quote::count())->toBe(count(QuoteTemplates::for('jamaah')));
        $page->call('showTopic', 'ilmu')->assertSee('QS. Taha: 114');
        Livewire::test(DisplayContents::class)->call('addTemplates', 'ilmu', 99)->assertNotFound();
        Livewire::test(DisplayContents::class)->call('showTopic', 'tidak-ada')->assertNotFound();
    });

    it('ayat terverifikasi bisa dinonaktifkan dan dihapus oleh penyetuju saja', function () {
        [$screen] = pairScreen();
        $verified = Quote::create(['text_ar' => 'وَقُلْ رَبِّ زِدْنِي عِلْمًا', 'text_id' => 'Tambahkan ilmu', 'source_ref' => 'QS. Taha: 114', 'verified_at' => now(), 'verified_by' => $this->takmir->id]);
        $draft = Quote::create(['text_ar' => 'نص', 'text_id' => 'Draf', 'source_ref' => 'Draf 1']);

        $this->actingAs($this->humas);
        Livewire::test(DisplayContents::class)->call('toggleQuote', $verified->uuid)->assertForbidden();
        Livewire::test(DisplayContents::class)->call('deleteQuote', $draft->uuid);
        expect(Quote::find($draft->id))->toBeNull();

        $this->actingAs($this->takmir);
        Livewire::test(DisplayContents::class)->set('tab', 'pustaka')->call('toggleQuote', $verified->uuid)->assertSee('Terverifikasi, nonaktif');
        expect(app(DisplayManifest::class)->build($screen)['quotes'])->toBe([]);
        Livewire::test(DisplayContents::class)->call('toggleQuote', $verified->uuid);
        expect(app(DisplayManifest::class)->build($screen)['quotes'])->toHaveCount(1);
        Livewire::test(DisplayContents::class)->call('deleteQuote', $verified->uuid);
        expect(Quote::count())->toBe(0)->and(app(DisplayManifest::class)->build($screen)['quotes'])->toBe([]);
    });

    it('PDF jadwal bulanan selalu sebulan penuh, Jumat ditandai, sudut tercetak benar', function () {
        $this->app->instance(PdfRenderer::class, new class implements PdfRenderer
        {
            public function render(string $html, string $format = 'A4'): string
            {
                return '%PDF'.$html;
            }
        });
        // Hanya tiga hari yang tersimpan; sisanya dihitung saat dicetak tanpa disimpan.
        app(ComputePrayerTimes::class)->handle(now()->toDateString(), 3);
        PrayerTime::query()->whereNotIn('id', PrayerTime::query()->orderBy('date')->limit(3)->pluck('id'))->delete();
        $month = now(config('sanad.display_timezone'))->format('Y-m');
        $html = $this->get(route('masjid.public.month', ['month' => $month]))->assertOk()->getContent();
        $days = CarbonImmutable::parse($month.'-01')->daysInMonth;

        expect(substr_count($html, '<td class="date'))->toBe($days)
            ->and(substr_count($html, 'class="friday"'))->toBeGreaterThanOrEqual(4)
            ->and($html)->toContain('Sudut Subuh 20°')->not->toContain('Hari ini')
            ->and(PrayerTime::count())->toBe(3);
    });

    it('pratinjau mengambil isi terbaru tanpa muat ulang', function () {
        [$screen] = pairScreen();
        $this->actingAs($this->takmir)->get(route('staff.masjid.preview', $screen))->assertOk()->assertSee('pratinjau\/isi', false);
        $first = $this->getJson(route('staff.masjid.preview-manifest', $screen))->assertOk()->json('version');
        $screen->update(['org_mode' => 'always']);
        expect($this->getJson(route('staff.masjid.preview-manifest', $screen))->json('version'))->not->toBe($first);
        $this->actingAs($this->humas)->getJson(route('staff.masjid.preview-manifest', $screen))->assertForbidden();
    });

    it('heartbeat, perintah jarak jauh, dan status offline', function () {
        [$screen, $token] = pairScreen();
        $auth = ['Authorization' => 'Bearer '.$token];
        $this->postJson('/api/v1/display/heartbeat', ['version' => 'abc', 'app_version' => 'tv-1', 'clock_offset_ms' => 180000], $auth)->assertOk();
        expect($screen->fresh()->isOnline())->toBeTrue()->and($screen->fresh()->clock_offset_ms)->toBe(180000);

        $this->actingAs($this->admin);
        Livewire::test(DisplayScreens::class)->assertSee('TV Utama')->assertSee('Online')->call('command', $screen->uuid, 'reload');
        $commands = $this->getJson('/api/v1/display/manifest/version', $auth)->assertOk()->json('commands');
        expect($commands[0]['command'])->toBe('reload');
        $this->postJson('/api/v1/display/command-ack', ['id' => $commands[0]['id']], $auth)->assertOk();
        expect($this->getJson('/api/v1/display/manifest/version', $auth)->json('commands'))->toBe([]);

        $this->travel(6)->minutes();
        expect($screen->fresh()->isOnline())->toBeFalse();
    });

    it('panel pairing: kode salah ditolak, kode benar memasang layar', function () {
        $pairing = app(ManageScreens::class)->startPairing();
        $this->actingAs($this->admin);
        Livewire::test(DisplayScreens::class)->set('code', '999999')->set('name', 'TV')->call('claim')->assertHasErrors('code')
            ->set('code', $pairing['code'])->call('claim')->assertHasNoErrors()->assertSee('TV');

        $this->actingAs($this->humas)->get(route('staff.masjid.screens'))->assertForbidden();
    });
});

describe('konten', function () {
    it('alur persetujuan; humas tidak bisa menyetujui; duka cita wajib orang lain; edit kembali menunggu', function () {
        $this->actingAs($this->humas);
        $page = Livewire::test(DisplayContents::class)->call('create')->set('title', 'Kajian Akbar')->set('text', 'Ahad pagi di aula')->call('save')->assertHasNoErrors();
        $content = DisplayContent::first();
        expect($content->status)->toBe('draft');
        $page->call('submit', $content->uuid);
        expect($content->fresh()->status)->toBe('pending');
        $page->call('approveContent', $content->uuid)->assertForbidden();

        $this->actingAs($this->takmir);
        Livewire::test(DisplayContents::class)->set('tab', 'persetujuan')->assertSee('Kajian Akbar')->call('approveContent', $content->uuid);
        expect($content->fresh()->status)->toBe('approved');

        $this->actingAs($this->humas);
        Livewire::test(DisplayContents::class)->call('edit', $content->uuid)->set('title', 'Kajian Akbar Diubah')->call('save');
        expect($content->fresh()->status)->toBe('pending');

        $obituary = DisplayContent::create(['type' => 'obituary', 'title' => 'Duka', 'body' => ['text' => 'x'], 'status' => 'pending', 'created_by' => $this->takmir->id]);
        expect(fn () => app(ManageContent::class)->approve($obituary, $this->takmir))->toThrow(MasjidException::class, 'orang lain');
        app(ManageContent::class)->approve($obituary, $this->admin);
        expect($obituary->fresh()->status)->toBe('approved');
    });

    it('validasi: teks panjang ditolak, poster butuh gambar, akhir setelah mulai', function () {
        $this->actingAs($this->humas);
        Livewire::test(DisplayContents::class)->call('create')->set('title', 'X')->set('text', str_repeat('kata ', 100))->call('save')->assertHasErrors('text')
            ->set('type', 'poster')->set('text', '')->call('save')->assertHasErrors('media')
            ->set('type', 'text')->set('text', 'ok')->set('starts_at', '2026-11-07T10:00')->set('ends_at', '2026-11-07T09:00')->call('save')->assertHasErrors('ends_at');
    });

    it('darurat: menimpa, wajib waktu akhir, dicabut satu tombol', function () {
        [$screen] = pairScreen();
        $this->actingAs($this->takmir);
        $page = Livewire::test(DisplayContents::class)->set('tab', 'darurat')->set('emergency_text', 'Banjir, santri berkumpul di aula')->call('sendEmergency')->assertHasErrors('emergency_until')
            ->set('emergency_until', '2026-11-06T09:00')->call('sendEmergency')->assertHasErrors('emergency_until')
            ->set('emergency_until', '2026-11-06T18:00')->call('sendEmergency')->assertHasNoErrors();

        expect(app(DisplayManifest::class)->build($screen)['emergency']['text'])->toBe('Banjir, santri berkumpul di aula');
        $page->call('clearEmergency');
        expect(app(DisplayManifest::class)->build($screen)['emergency'])->toBeNull();

        $this->actingAs($this->humas);
        Livewire::test(DisplayContents::class)->call('sendEmergency')->assertForbidden();
    });

    it('pengumuman SANAD ditandai tayang di TV: ikut berubah, tanpa salinan ganda, terhapus ikut hilang', function () {
        [$screen] = pairScreen();
        $this->actingAs($this->admin);
        $page = Livewire::test(AnnouncementIndex::class)->call('create')->set('title', 'Libur')->set('body', 'Libur tanggal 10')->set('show_on_tv', true)->call('save')->assertHasNoErrors();
        expect(app(DisplayManifest::class)->build($screen)['contents'][0]['title'])->toBe('Libur');

        $announcement = Announcement::first();
        $page->call('edit', $announcement->uuid)->assertSet('show_on_tv', true)->set('title', 'Libur Panjang')->call('save');
        expect(DisplayContent::count())->toBe(1)->and(app(DisplayManifest::class)->build($screen)['contents'][0]['title'])->toBe('Libur Panjang');

        $page->call('delete', $announcement->uuid);
        expect(app(DisplayManifest::class)->build($screen)['contents'])->toBe([]);
    });
});

it('profil masjid baru langsung punya nilai bawaan dan layar pengaturan terbuka', function () {
    DB::table('masjid_profile')->delete();
    expect(MasjidProfile::current()->asr_method)->toBe('standard')->and(MasjidProfile::current()->fajr_angle)->toBe(20.0);
    $this->actingAs($this->admin)->get(route('staff.masjid.index', ['tab' => 'lokasi']))->assertOk();
});

describe('akses dan publik', function () {
    it('halaman publik jadwal dan PDF; konten internal tidak tampil', function () {
        content(['title' => 'Internal saja']);
        content(['title' => 'Untuk jamaah', 'is_public' => true]);
        $this->get(route('masjid.public'))->assertOk()->assertSee('Masjid Uji')->assertSee('Subuh')->assertSee('Untuk jamaah')->assertDontSee('Internal saja');

        Module::query()->where('module_key', 'masjid_public')->update(['is_enabled' => false]);
        app(ModuleRegistry::class)->forget();
        $this->get(route('masjid.public'))->assertForbidden();
    });

    it('peran: ustadz tidak bisa membuka panel masjid; humas tidak bisa mengubah lokasi; modul mati menutup layar', function () {
        $this->actingAs($this->ustadz)->get(route('staff.masjid.index'))->assertForbidden();
        $this->actingAs($this->takmir);
        Livewire::test(MasjidSettings::class)->set('tab', 'lokasi')->call('saveProfile')->assertForbidden();
        $this->actingAs($this->humas)->get(route('staff.masjid.contents'))->assertOk();

        Module::query()->where('module_key', 'masjid_display')->update(['is_enabled' => false]);
        app(ModuleRegistry::class)->forget();
        $this->get('/display')->assertForbidden();
    });

    it('kartu waktu sholat berikutnya di beranda santri', function () {
        $user = tap(User::create(['name' => 'Santri', 'username' => 'S9', 'type' => 'student']))->assignRole('santri');
        Student::create(['nis' => 'S9', 'name' => 'Santri', 'gender' => 'L', 'user_id' => $user->id]);
        $this->actingAs($user)->get(route('santri.home'))->assertOk()->assertSee('Sholat berikutnya')->assertSee('Dzuhur');
    });
});

describe('konten fleksibel', function () {
    it('tautan YouTube dikenali dari berbagai bentuk', function () {
        expect(YoutubeLink::id('https://www.youtube.com/watch?v=dQw4w9WgXcQ&t=10'))->toBe('dQw4w9WgXcQ')
            ->and(YoutubeLink::id('https://youtu.be/dQw4w9WgXcQ?si=abc'))->toBe('dQw4w9WgXcQ')
            ->and(YoutubeLink::id('https://youtube.com/shorts/dQw4w9WgXcQ'))->toBe('dQw4w9WgXcQ')
            ->and(YoutubeLink::id('https://www.youtube.com/live/dQw4w9WgXcQ'))->toBe('dQw4w9WgXcQ')
            ->and(YoutubeLink::id('https://m.youtube.com/watch?v=dQw4w9WgXcQ'))->toBe('dQw4w9WgXcQ')
            ->and(YoutubeLink::id('https://evil.test/watch?v=dQw4w9WgXcQ'))->toBeNull()
            ->and(YoutubeLink::id('bukan tautan'))->toBeNull();
    });

    it('YouTube layar penuh langsung tayang oleh takmir dan sampai ke manifest; tautan salah ditolak', function () {
        [$screen] = pairScreen();
        $this->actingAs($this->takmir);
        Livewire::test(DisplayContents::class)->call('create')->set('type', 'youtube')->set('layout', 'full')->set('title', 'Kajian')
            ->set('youtube_url', 'https://example.com/video')->call('save', true)->assertHasErrors('youtube_url')
            ->set('youtube_url', 'https://youtu.be/dQw4w9WgXcQ')->set('duration', '1800')->call('save', true)->assertHasNoErrors();

        $content = DisplayContent::firstOrFail();
        expect($content->status)->toBe('approved')->and($content->layout)->toBe('full')->and($content->body['youtube_id'])->toBe('dQw4w9WgXcQ');

        $item = collect(app(DisplayManifest::class)->build($screen)['contents'])->firstWhere('id', $content->uuid);
        expect($item['layout'])->toBe('full')->and($item['type'])->toBe('youtube')->and($item['duration'])->toBe(1800);
    });

    it('humas tidak bisa langsung tayang; teks panjang hanya untuk layar penuh; gambar hasil potongan tersimpan utuh', function () {
        Storage::fake('local');
        $this->actingAs($this->humas);
        $long = str_repeat('Kalimat panjang untuk layar penuh. ', 15);
        Livewire::test(DisplayContents::class)->call('create')->set('title', 'Info')->set('text', $long)->call('save', true)->assertHasErrors('text')
            ->set('layout', 'full')->call('save', true)->assertHasNoErrors();
        expect(DisplayContent::firstOrFail()->status)->toBe('draft');

        Livewire::test(DisplayContents::class)->call('create')->set('type', 'poster')->set('title', 'Poster')
            ->set('media', UploadedFile::fake()->image('konten.jpg', 1920, 1080))->call('save')->assertHasNoErrors();
        [$width] = getimagesize(Storage::disk('local')->path(DisplayContent::where('type', 'poster')->first()->media_path));
        expect($width)->toBe(1920);
    });

    it('perintah ke layar offline memberi tahu; panel menampilkan perintah terakhir', function () {
        [$screen] = pairScreen();
        $this->actingAs($this->admin);
        Livewire::test(DisplayScreens::class)->call('command', $screen->uuid, 'dim')
            ->assertDispatched('toast', message: 'Layar sedang offline. Perintah dijalankan saat layar tersambung lagi.', tone: 'warning')
            ->assertSee('Perintah terakhir: redupkan, menunggu layar.');
    });

    it('layar TV tidak muncul di daftar perangkat pemindai', function () {
        pairScreen(['name' => 'TV Rahasia']);
        Module::where('module_key', 'card')->update(['is_enabled' => true]);
        app(ModuleRegistry::class)->forget();
        $this->actingAs($this->admin)->get(route('staff.cards.devices'))->assertOk()->assertDontSee('TV Rahasia');
    });
});

it('layar terpasang bisa diubah tanpa dipasangkan ulang; manifest ikut berubah', function () {
    [$screen] = pairScreen(['name' => 'TV Lama']);
    $before = app(DisplayManifest::class)->build($screen)['version'];
    $this->actingAs($this->admin);

    Livewire::test(DisplayScreens::class)->call('editScreen', $screen->uuid)->assertSet('name', 'TV Lama')
        ->set('name', 'TV Aula')->set('profile', 'announcement_only')->set('dim_from', '22:00')->call('updateScreen')->assertHasErrors('dim_until')
        ->set('dim_until', '04:00')->call('updateScreen')->assertHasNoErrors()->assertSee('TV Aula');

    $screen->refresh();
    expect($screen->profile)->toBe('announcement_only')->and($screen->brightness_schedule['from'])->toBe('22:00')
        ->and($screen->device->name)->toBe('TV Aula')
        ->and(app(DisplayManifest::class)->build($screen)['version'])->not->toBe($before);
});
