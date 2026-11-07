<?php

use App\Modules\Core\Actions\RequestLoginOtp;
use App\Modules\Core\Actions\SaveIntegration;
use App\Modules\Core\Actions\StartSession;
use App\Modules\Core\Livewire\Announcements\AnnouncementIndex;
use App\Modules\Core\Livewire\Settings\IntegrationSettings;
use App\Modules\Core\Models\Announcement;
use App\Modules\Core\Models\Guardian;
use App\Modules\Core\Models\InAppNotification;
use App\Modules\Core\Models\Module;
use App\Modules\Core\Models\Student;
use App\Modules\Core\Models\User;
use App\Modules\Core\Models\WebhookEvent;
use App\Modules\Core\Support\ModuleRegistry;
use App\Modules\Finance\Actions\ApplySettlement;
use App\Modules\Finance\Actions\CreateManualInvoice;
use App\Modules\Finance\Actions\CreateManualPayment;
use App\Modules\Finance\Actions\GenerateInvoices;
use App\Modules\Finance\Actions\RecordCashPayment;
use App\Modules\Finance\Actions\RejectManualPayment;
use App\Modules\Finance\Actions\SubmitPaymentProof;
use App\Modules\Finance\Livewire\Reports\ArrearsReport;
use App\Modules\Finance\Models\BankAccount;
use App\Modules\Finance\Models\FeeRate;
use App\Modules\Finance\Models\FeeType;
use App\Modules\Notification\Actions\QueueNotification;
use App\Modules\Notification\Actions\SendAnnouncement;
use App\Modules\Notification\Actions\SendPaymentReminders;
use App\Modules\Notification\Jobs\SendOtpMessage;
use App\Modules\Notification\Jobs\SendWhatsAppMessage;
use App\Modules\Notification\Livewire\GuardianMore;
use App\Modules\Notification\Livewire\Templates;
use App\Modules\Notification\Models\GuardianNotificationPref;
use App\Modules\Notification\Models\NotificationLog;
use App\Modules\Notification\Models\NotificationTemplate;
use App\Modules\Notification\Support\FonnteClient;
use Carbon\CarbonImmutable;
use Database\Seeders\ModuleSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    (new ModuleSeeder)->run();
    (new RoleSeeder)->run();
    Storage::fake('local');
    Module::where('module_key', 'notification_wa')->update(['is_enabled' => true]);
    app(ModuleRegistry::class)->forget();
    app(SaveIntegration::class)->handle('fonnte', null, ['token' => 'token-uji'], ['hourly_limit' => 60, 'webhook_secret' => 'rahasia-webhook'], true);
    RateLimiter::clear('fonnte-hourly');
    Queue::fake();
    $this->travelTo('2026-11-02 09:00:00');

    $this->student = Student::create(['nis' => 'A1', 'name' => 'Ahmad', 'gender' => 'L']);
    $this->guardianUser = tap(User::create(['name' => 'Bapak Ahmad', 'phone' => '6281200000001', 'type' => 'guardian']))->assignRole('wali');
    $this->guardian = Guardian::create(['user_id' => $this->guardianUser->id, 'name' => 'Bapak Ahmad', 'phone_wa' => '6281200000001']);
    $this->guardian->students()->attach($this->student->id, ['relation' => 'ayah', 'is_primary' => true]);
    $this->invoice = app(CreateManualInvoice::class)->handle($this->student, [['description' => 'SPP', 'amount' => 625000]], '2026-11-10');
    $this->bendahara = tap(User::create(['name' => 'Bendahara', 'email' => 'b@uji.test', 'phone' => '6281300000001', 'type' => 'staff']))->assignRole('bendahara');
});

it('tagihan terbit mengirim WA lewat antrean dengan isi dan tautan bayar', function () {
    $type = FeeType::create(['code' => 'SPP', 'name' => 'SPP', 'category' => 'monthly', 'due_day' => 10]);
    FeeRate::create(['fee_type_id' => $type->id, 'scope' => 'default', 'amount' => 625000, 'valid_from' => '2026-01-01']);

    app(GenerateInvoices::class)->run(CarbonImmutable::parse('2026-11-01'));

    $log = NotificationLog::where('event_key', 'invoice_issued')->first();
    expect($log->recipient)->toBe('6281200000001')
        ->and($log->status)->toBe('queued')
        ->and($log->payload['message'])->toContain('Rp 625.000')->toContain('Ahmad')->toContain('/wali/tagihan/');

    Queue::assertPushedOn('notifications', SendWhatsAppMessage::class);
    Http::assertNothingSent();
    expect(InAppNotification::where('user_id', $this->guardianUser->id)->exists())->toBeTrue();
});

it('jeda antar pesan acak dan berurutan, tidak serentak', function () {
    $slots = collect(range(1, 5))->map(fn () => QueueNotification::nextSlot()->getTimestamp());

    $gaps = $slots->sliding(2)->map(fn ($pair) => $pair->last() - $pair->first());

    expect($gaps->every(fn ($gap) => $gap >= 4))->toBeTrue();
});

it('pembayaran berhasil memberi tahu wali; tunai juga', function () {
    app(RecordCashPayment::class)->handle([$this->invoice->id], 625000, $this->bendahara->id);

    $log = NotificationLog::where('event_key', 'payment_success')->first();
    expect($log->payload['message'])->toContain('tunai')->toContain('Rp 625.000');
});

it('bukti masuk memberi tahu bendahara, penolakan memberi tahu wali dengan alasan', function () {
    $account = BankAccount::create(['bank_name' => 'B', 'account_no' => '1', 'account_holder' => 'P']);
    $payment = app(CreateManualPayment::class)->handle(collect([$this->invoice]), $account, null, null);

    app(SubmitPaymentProof::class)->handle($payment, UploadedFile::fake()->image('a.jpg'));
    expect(NotificationLog::where('event_key', 'proof_submitted')->value('recipient'))->toBe('6281300000001');

    app(RejectManualPayment::class)->handle($payment, 'Nominal kurang', $this->bendahara->id);
    expect(NotificationLog::where('event_key', 'proof_rejected')->first()->payload['message'])->toContain('Nominal kurang');
});

it('pesan tidak dikirim saat transaksi batal', function () {
    $account = BankAccount::create(['bank_name' => 'B', 'account_no' => '1', 'account_holder' => 'P']);
    $payment = app(CreateManualPayment::class)->handle(collect([$this->invoice]), $account, null, null);
    $payment->update(['status' => 'review']);

    try {
        DB::transaction(function () use ($payment) {
            app(ApplySettlement::class)->handle($payment, $this->bendahara->id);
            throw new RuntimeException('batal');
        });
    } catch (RuntimeException) {
    }

    expect(NotificationLog::where('event_key', 'payment_success')->count())->toBe(0);
});

it('job mengirim ke Fonnte dengan format resmi dan menyimpan id pesan', function () {
    Http::fake(['api.fonnte.com/send' => Http::response(['status' => true, 'id' => ['99123'], 'detail' => 'success! message in queue', 'process' => 'pending'])]);
    $log = NotificationLog::create(['recipient' => '6281200000001', 'event_key' => 'invoice_issued', 'payload' => ['message' => 'Halo']]);

    (new SendWhatsAppMessage($log))->handle(app(FonnteClient::class));

    Http::assertSent(fn (HttpRequest $request) => $request->url() === 'https://api.fonnte.com/send'
        && $request->hasHeader('Authorization', 'token-uji')
        && $request['target'] === '6281200000001'
        && $request['message'] === 'Halo'
        && $request['countryCode'] === '62');

    expect($log->fresh()->status)->toBe('sent')->and($log->fresh()->provider_message_id)->toBe('99123')->and($log->fresh()->sent_at)->not->toBeNull();
});

it('gagal diulang sampai 3 kali lalu ditandai failed dengan alasan', function () {
    Http::fake(['api.fonnte.com/send' => Http::response(['status' => false, 'reason' => 'insufficient quota'])]);
    $log = NotificationLog::create(['recipient' => '62812', 'event_key' => 'invoice_issued', 'payload' => ['message' => 'Halo']]);

    foreach (range(1, 3) as $attempt) {
        $job = (new SendWhatsAppMessage($log))->withFakeQueueInteractions();
        $job->handle(app(FonnteClient::class));
    }

    expect($log->fresh()->status)->toBe('failed')->and($log->fresh()->attempts)->toBe(3)->and($log->fresh()->error)->toBe('insufficient quota');
});

it('menahan pengiriman saat batas per jam tercapai tanpa menghitung percobaan', function () {
    app(SaveIntegration::class)->handle('fonnte', null, [], ['hourly_limit' => 1, 'webhook_secret' => 'x'], true);
    Http::fake(['api.fonnte.com/send' => Http::response(['status' => true, 'id' => ['1']])]);
    $first = NotificationLog::create(['recipient' => '1', 'event_key' => 'invoice_issued', 'payload' => ['message' => 'a']]);
    $second = NotificationLog::create(['recipient' => '2', 'event_key' => 'invoice_issued', 'payload' => ['message' => 'b']]);

    (new SendWhatsAppMessage($first))->withFakeQueueInteractions()->handle(app(FonnteClient::class));
    $job = (new SendWhatsAppMessage($second))->withFakeQueueInteractions();
    $job->handle(app(FonnteClient::class));

    $job->assertReleased();
    expect($first->fresh()->status)->toBe('sent')->and($second->fresh()->status)->toBe('queued')->and($second->fresh()->attempts)->toBe(0);
    Http::assertSentCount(1);
});

it('tidak mengirim dua kali bila job berjalan ulang setelah terkirim', function () {
    Http::fake(['api.fonnte.com/send' => Http::response(['status' => true, 'id' => ['1']])]);
    $log = NotificationLog::create(['recipient' => '1', 'event_key' => 'invoice_issued', 'payload' => ['message' => 'a']]);

    (new SendWhatsAppMessage($log))->handle(app(FonnteClient::class));
    (new SendWhatsAppMessage($log))->handle(app(FonnteClient::class));

    Http::assertSentCount(1);
});

it('modul WA mati: notifikasi aplikasi tetap masuk, WA tidak', function () {
    Module::where('module_key', 'notification_wa')->update(['is_enabled' => false]);
    app(ModuleRegistry::class)->forget();

    app(RecordCashPayment::class)->handle([$this->invoice->id], 625000, $this->bendahara->id);

    expect(NotificationLog::count())->toBe(0)->and(InAppNotification::where('user_id', $this->guardianUser->id)->count())->toBe(1);
});

it('template mati tidak mengirim apa pun', function () {
    NotificationTemplate::create(['event_key' => 'payment_success', 'body' => 'x', 'is_enabled' => false]);

    app(RecordCashPayment::class)->handle([$this->invoice->id], 625000, $this->bendahara->id);

    expect(NotificationLog::where('event_key', 'payment_success')->count())->toBe(0);
});

it('wali yang dilarang menerima WA tidak dikirimi', function () {
    $this->guardian->students()->updateExistingPivot($this->student->id, ['can_receive_wa' => false]);

    app(RecordCashPayment::class)->handle([$this->invoice->id], 625000, $this->bendahara->id);

    expect(NotificationLog::count())->toBe(0);
});

it('pengingat H-3, H0, H+3, H+7 berjalan sekali per tahap dan melewati yang lunas', function () {
    $reminders = app(SendPaymentReminders::class);

    $this->travelTo('2026-11-06 01:00:00');
    expect($reminders->handle())->toBe(0);

    $this->travelTo('2026-11-07 01:00:00');
    expect($reminders->handle())->toBe(1);
    expect($reminders->handle())->toBe(0);

    $this->travelTo('2026-11-10 01:00:00');
    expect($reminders->handle())->toBe(1);

    $this->travelTo('2026-11-13 01:00:00');
    expect($reminders->handle())->toBe(1);

    app(RecordCashPayment::class)->handle([$this->invoice->id], 625000, $this->bendahara->id);
    $this->travelTo('2026-11-17 01:00:00');
    expect($reminders->handle())->toBe(0);
});

it('pengingat massal dari layar tunggakan: pratinjau, jumlah, lalu kirim', function () {
    $this->travelTo('2026-11-20 09:00:00');
    $this->actingAs($this->bendahara);

    Livewire::test(ArrearsReport::class)
        ->call('prepareReminder')
        ->assertSee('1 tagihan')
        ->assertSee('Rp 625.000')
        ->call('sendReminder')
        ->assertDispatched('toast');

    expect(NotificationLog::where('event_key', 'payment_reminder')->count())->toBe(1);

    $this->actingAs(tap(User::create(['name' => 'A', 'email' => 'a@uji.test', 'type' => 'staff']))->assignRole('admin_akademik'));
    Livewire::test(ArrearsReport::class)->call('sendReminder')->assertForbidden();
});

it('webhook Fonnte memerlukan token rahasia dan memperbarui status', function () {
    $log = NotificationLog::create(['recipient' => '1', 'event_key' => 'invoice_issued', 'payload' => ['message' => 'a'], 'status' => 'sent', 'provider_message_id' => '777']);

    $this->postJson('/webhooks/fonnte/salah', ['id' => '777', 'state' => 'delivered'])->assertForbidden();
    expect($log->fresh()->status)->toBe('sent');

    $this->postJson('/webhooks/fonnte/rahasia-webhook', ['id' => '777', 'state' => 'read', 'stateid' => 'x'])->assertOk();
    expect($log->fresh()->status)->toBe('delivered');

    $this->postJson('/webhooks/fonnte/rahasia-webhook', ['id' => '777', 'state' => 'failed'])->assertOk();
    expect($log->fresh()->status)->toBe('delivered')->and(WebhookEvent::where('provider', 'fonnte')->count())->toBe(2);
});

it('OTP lewat Fonnte terenkripsi di antrean dan tidak masuk log notifikasi', function () {
    app(RequestLoginOtp::class)->handle('6281200000001');

    Queue::assertPushedOn('otp', SendOtpMessage::class);
    expect(NotificationLog::count())->toBe(0)
        ->and(in_array(ShouldBeEncrypted::class, class_implements(SendOtpMessage::class), true))->toBeTrue();
});

it('template: variabel tak dikenal ditolak, teks tersimpan dipakai, pratinjau terisi', function () {
    $admin = tap(User::create(['name' => 'A', 'email' => 'adm@uji.test', 'type' => 'staff']))->assignRole('admin');
    $this->actingAs($admin);

    Livewire::test(Templates::class)
        ->call('edit', 'payment_success')
        ->set('body', 'Halo {nama_wali} {salah}')
        ->call('save')
        ->assertHasErrors('body')
        ->assertSee('Variabel tidak dikenal')
        ->set('body', 'Diterima {nominal} untuk {nama_santri}')
        ->assertSee('Rp 625.000')
        ->call('insertVariable', 'metode')
        ->assertSet('body', 'Diterima {nominal} untuk {nama_santri} {metode}')
        ->call('save')
        ->assertHasNoErrors();

    app(RecordCashPayment::class)->handle([$this->invoice->id], 625000, $this->bendahara->id);

    expect(NotificationLog::where('event_key', 'payment_success')->first()->payload['message'])->toBe('Diterima Rp 625.000 untuk Ahmad tunai');
});

it('log gagal bisa diulang dari layar, hanya admin yang membuka', function () {
    $admin = tap(User::create(['name' => 'A', 'email' => 'adm2@uji.test', 'type' => 'staff']))->assignRole('admin');
    $log = NotificationLog::create(['recipient' => '1', 'event_key' => 'invoice_issued', 'payload' => ['message' => 'a'], 'status' => 'failed', 'error' => 'quota', 'attempts' => 3]);

    $this->actingAs($this->bendahara)->get(route('staff.settings.notifications'))->assertForbidden();

    $this->actingAs($admin);
    Livewire::test(Templates::class)->call('selectTab', 'log')->assertSee('quota')->call('retry', $log->uuid)->assertDispatched('toast');

    expect($log->fresh()->status)->toBe('queued')->and($log->fresh()->attempts)->toBe(0);
    Queue::assertPushed(SendWhatsAppMessage::class);
});

it('preferensi wali: kategori kritis terkunci, pengumuman bisa dimatikan', function () {
    $this->actingAs($this->guardianUser);

    Livewire::test(GuardianMore::class)
        ->assertSee('Selalu dikirim')
        ->call('togglePref', 'billing')
        ->assertForbidden();

    Livewire::test(GuardianMore::class)->call('togglePref', 'announcement');
    expect(GuardianNotificationPref::where('category', 'announcement')->value('is_enabled'))->toBeFalse();

    app(QueueNotification::class)->toGuardians('announcement', [$this->student], fn () => ['judul' => 'J', 'isi' => 'I'], 'J');
    expect(NotificationLog::where('event_key', 'announcement')->count())->toBe(0);
});

it('wali hanya melihat notifikasi miliknya', function () {
    InAppNotification::create(['user_id' => $this->bendahara->id, 'title' => 'Rahasia staf']);
    InAppNotification::create(['user_id' => $this->guardianUser->id, 'title' => 'Untuk wali']);

    $this->actingAs($this->guardianUser);

    Livewire::test(GuardianMore::class)->assertSee('Untuk wali')->assertDontSee('Rahasia staf');
});

it('tes koneksi Fonnte melaporkan perangkat dan token salah tanpa membocorkan token', function () {
    $this->actingAs(tap(User::create(['name' => 'A', 'email' => 'adm3@uji.test', 'type' => 'staff']))->assignRole('admin'));
    Http::fake(['api.fonnte.com/device' => Http::sequence()
        ->push(['status' => true, 'device' => '62811', 'device_status' => 'connect', 'quota' => '1000'])
        ->push(['status' => true, 'device' => '62811', 'device_status' => 'disconnect'])
        ->push(['status' => false, 'reason' => 'token invalid'])]);

    $component = Livewire::test(IntegrationSettings::class);
    $component->call('testFonnte')->assertSee('sisa kuota 1000')->assertDontSee('token-uji');
    $component->call('testFonnte')->assertSee('belum tersambung');
    $component->call('testFonnte')->assertSee('Token ditolak');
});

it('tes kirim Fonnte mengirim ke nomor yang diisi', function () {
    $this->actingAs(tap(User::create(['name' => 'A', 'email' => 'adm4@uji.test', 'type' => 'staff']))->assignRole('admin'));
    Http::fake(['api.fonnte.com/send' => Http::response(['status' => true, 'id' => ['1']])]);

    Livewire::test(IntegrationSettings::class)
        ->set('fonnte_test_phone', '12')
        ->call('testFonnteSend')
        ->assertHasErrors('fonnte_test_phone')
        ->set('fonnte_test_phone', '081200000009')
        ->call('testFonnteSend')
        ->assertHasNoErrors()
        ->assertSee('Pesan uji terkirim');

    Http::assertSent(fn (HttpRequest $request) => $request['target'] === '6281200000009');
});

it('kegagalan notifikasi tidak menggagalkan pembayaran yang sudah tercatat', function () {
    $this->mock(QueueNotification::class)->shouldReceive('toGuardians')->andThrow(new RuntimeException('redis mati'));

    app(RecordCashPayment::class)->handle([$this->invoice->id], 625000, $this->bendahara->id);

    expect($this->invoice->fresh()->status)->toBe('paid');
});

it('job pengingat berjalan ulang tidak menggandakan notifikasi aplikasi, walau modul WA mati', function () {
    Module::where('module_key', 'notification_wa')->update(['is_enabled' => false]);
    app(ModuleRegistry::class)->forget();
    $this->travelTo('2026-11-07 01:00:00');

    app(SendPaymentReminders::class)->handle();
    app(SendPaymentReminders::class)->handle();

    expect(InAppNotification::where('user_id', $this->guardianUser->id)->count())->toBe(1);
});

it('pengumuman WA: langsung saat tayang, terjadwal dikirim sekali saat waktunya, ubah tidak mematikan jadwal', function () {
    $this->actingAs(tap(User::create(['name' => 'A', 'email' => 'adm5@uji.test', 'type' => 'staff']))->assignRole('admin'));

    Livewire::test(AnnouncementIndex::class)
        ->set('title', 'Libur')->set('body', 'Libur pekan depan')->set('send_wa', true)
        ->call('save')->assertHasNoErrors();

    expect(NotificationLog::where('event_key', 'announcement')->first()->payload['message'])->toContain('Libur pekan depan');

    $scheduled = Livewire::test(AnnouncementIndex::class)
        ->set('title', 'Rapat')->set('body', 'Rapat wali')->set('send_wa', true)->set('publish_at', '2026-11-03T08:00')
        ->call('save')->assertHasNoErrors();

    expect(NotificationLog::where('event_key', 'announcement')->count())->toBe(1);

    $announcement = Announcement::where('title', 'Rapat')->first();
    $scheduled->call('edit', $announcement->uuid)->set('title', 'Rapat Wali')->call('save')->assertHasNoErrors();
    expect($announcement->fresh()->send_wa)->toBeTrue();

    $this->travelTo('2026-11-03 02:00:00');
    expect(app(SendAnnouncement::class)->due())->toBe(1)
        ->and(app(SendAnnouncement::class)->due())->toBe(0);
});

it('tautan dari WA kembali ke tujuan setelah masuk, tetapi bukan ke wilayah peran lain', function () {
    session(['url.intended' => route('wali.invoices')]);
    expect(app(StartSession::class)->handle($this->guardianUser))->toBe(route('wali.invoices'));

    Auth::logout();
    session(['url.intended' => route('staff.dashboard')]);
    expect(app(StartSession::class)->handle($this->guardianUser))->toBe(route('wali.home'));
});

it('dasbor antrean hanya untuk pengelola pengaturan', function () {
    expect($this->bendahara->can('viewHorizon'))->toBeFalse()
        ->and(tap(User::create(['name' => 'A', 'email' => 'adm6@uji.test', 'type' => 'staff']))->assignRole('admin')->can('viewHorizon'))->toBeTrue();
});

it('lonceng portal menampilkan jumlah belum dibaca, hanya untuk wali', function () {
    InAppNotification::create(['user_id' => $this->guardianUser->id, 'title' => 'Baru']);

    $this->actingAs($this->guardianUser)->get(route('wali.invoices'))->assertOk()->assertSee('Notifikasi, 1 belum dibaca');

    Livewire::actingAs($this->guardianUser)->test(GuardianMore::class)->call('markAllRead');
    $this->get(route('wali.invoices'))->assertDontSee('belum dibaca');
});
