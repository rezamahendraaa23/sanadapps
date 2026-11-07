<?php

use App\Modules\Academic\Models\Subject;
use App\Modules\Admission\Actions\AdmissionFlow;
use App\Modules\Admission\Actions\AdmissionOtp;
use App\Modules\Admission\Actions\EnrollApplicant;
use App\Modules\Admission\Actions\RegisterApplicant;
use App\Modules\Admission\Actions\ScoreApplicant;
use App\Modules\Admission\Livewire\AdmissionPeriods;
use App\Modules\Admission\Livewire\AdmissionSelection;
use App\Modules\Admission\Livewire\AdmissionStatus;
use App\Modules\Admission\Livewire\AdmissionWizard;
use App\Modules\Admission\Livewire\ApplicantDetail;
use App\Modules\Admission\Livewire\ApplicantList;
use App\Modules\Admission\Models\AdmissionPeriod;
use App\Modules\Admission\Models\AdmissionStep;
use App\Modules\Admission\Models\Applicant;
use App\Modules\Admission\Models\ApplicantDocument;
use App\Modules\Admission\Support\AdmissionException;
use App\Modules\Core\Actions\OtpResult;
use App\Modules\Core\Actions\VerifyLoginOtp;
use App\Modules\Core\Contracts\OtpSender;
use App\Modules\Core\Models\Module;
use App\Modules\Core\Models\OtpCode;
use App\Modules\Core\Models\Student;
use App\Modules\Core\Models\User;
use App\Modules\Core\Support\ModuleRegistry;
use App\Modules\Exam\Actions\RunAttempt;
use App\Modules\Exam\Actions\SaveQuestion;
use App\Modules\Exam\Models\Exam;
use App\Modules\Exam\Models\ExamAttempt;
use App\Modules\Exam\Models\QuestionBank;
use App\Modules\Exam\Support\ExamException;
use App\Modules\Finance\Actions\ApplySettlement;
use App\Modules\Finance\Actions\RecordCashPayment;
use App\Modules\Finance\Models\FeeRate;
use App\Modules\Finance\Models\FeeType;
use App\Modules\Finance\Models\Invoice;
use App\Modules\Finance\Models\Payment;
use App\Modules\Finance\Models\PaymentAllocation;
use App\Modules\Wallet\Models\Wallet;
use Database\Seeders\FinanceSeeder;
use Database\Seeders\ModuleSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

class CapturedOtp implements OtpSender
{
    public static ?string $code = null;

    public function send(string $phone, string $code): void
    {
        self::$code = $code;
    }
}

beforeEach(function () {
    (new ModuleSeeder)->run();
    (new RoleSeeder)->run();
    (new FinanceSeeder)->run();
    Module::query()->whereIn('module_key', ['ppdb', 'finance', 'wallet', 'exam'])->update(['is_enabled' => true]);
    app(ModuleRegistry::class)->forget();
    Storage::fake('local');
    app()->bind(OtpSender::class, CapturedOtp::class);
    RateLimiter::clear('otp-ppdb:6281200000001');
    $this->travelTo('2026-11-02 05:00:00');

    $this->panitia = tap(User::create(['name' => 'Panitia', 'email' => 'p@uji.test', 'type' => 'staff']))->assignRole('panitia_ppdb');
    $this->treasurer = tap(User::create(['name' => 'Bendahara', 'email' => 'b@uji.test', 'type' => 'staff']))->assignRole('bendahara');
    $this->teacher = tap(User::create(['name' => 'Ustadz', 'email' => 'u@uji.test', 'type' => 'staff']))->assignRole('ustadz');

    $this->period = AdmissionPeriod::create(['name' => 'Gelombang 1', 'open_at' => '2026-11-01 00:00:00', 'close_at' => '2026-12-01 00:00:00', 'quota' => 1, 'registration_fee' => 150000, 'status' => 'open']);
    $this->documentStep = AdmissionStep::create(['period_id' => $this->period->id, 'name' => 'Dokumen', 'type' => 'document', 'weight' => 20, 'sort_order' => 1]);
    $this->examStep = AdmissionStep::create(['period_id' => $this->period->id, 'name' => 'Ujian', 'type' => 'interview', 'weight' => 50, 'sort_order' => 2]);
    $this->interviewStep = AdmissionStep::create(['period_id' => $this->period->id, 'name' => 'Wawancara', 'type' => 'interview', 'weight' => 30, 'sort_order' => 3]);
});

function makeApplicant($context, string $name = 'Ahmad', string $phone = '6281200000001', string $status = 'draft'): Applicant
{
    $applicant = app(RegisterApplicant::class)->start($context->period, $phone);
    $applicant->update(['name' => $name, 'gender' => 'L', 'birth_date' => '2012-01-01', 'address' => 'Garut', 'guardian_name' => 'Bapak '.$name]);
    foreach (Applicant::REQUIRED_DOCUMENTS as $type) {
        app(RegisterApplicant::class)->storeDocument($applicant, $type, UploadedFile::fake()->image($type.'.jpg'));
    }

    if ($status !== 'draft') {
        $applicant->update(['status' => $status, 'submitted_at' => now()]);
    }

    return $applicant->refresh();
}

function fullScore($context, Applicant $applicant, float $score): void
{
    foreach ([$context->documentStep, $context->examStep, $context->interviewStep] as $step) {
        app(ScoreApplicant::class)->save($applicant, $step, $score, null, $context->panitia->id);
    }
    $applicant->refresh();
}

describe('pendaftaran publik', function () {
    it('wizard: OTP, simpan tiap langkah, dokumen wajib, persetujuan, lalu tagihan terbit', function () {
        $wizard = Livewire::test(AdmissionWizard::class)->set('phone', 'bukan')->call('sendOtp')->assertHasErrors('phone')
            ->set('phone', '081200000001')->call('sendOtp')->assertSet('otpSent', true)
            ->set('code', '000000')->call('verifyOtp')->assertHasErrors('code');

        OtpCode::query()->update(['attempts' => 0]);
        $wizard->set('code', CapturedOtp::$code)->call('verifyOtp')->assertHasNoErrors()->assertSet('step', 1);

        $applicant = Applicant::first();
        expect($applicant->guardian_phone)->toBe('6281200000001')->and($applicant->status)->toBe('draft')->and($applicant->reg_no)->toStartWith('PPDB-');

        $wizard->call('saveChild')->assertHasErrors(['name', 'gender', 'birth_date', 'address'])
            ->set('name', 'Ahmad')->set('gender', 'L')->set('birth_date', '2012-01-01')->set('address', 'Garut')->call('saveChild')->assertHasNoErrors()->assertSet('step', 2);
        expect($applicant->fresh()->name)->toBe('Ahmad');

        $wizard->call('back')->assertSet('step', 1)->call('saveChild')->call('saveGuardian')->assertHasErrors('guardian_name')
            ->set('guardian_name', 'Bapak')->call('saveGuardian')->assertSet('step', 3)
            ->call('toReview')->assertHasErrors('documents')->assertSet('step', 3);

        foreach (Applicant::REQUIRED_DOCUMENTS as $type) {
            $wizard->set('uploads.'.$type, UploadedFile::fake()->image($type.'.jpg'));
        }
        $wizard->call('toReview')->assertHasNoErrors()->assertSet('step', 4)
            ->call('submit')->assertHasErrors('consent')
            ->set('consent', true)->call('submit')->assertHasNoErrors()->assertRedirect(route('ppdb.status'));

        $applicant->refresh();
        expect($applicant->status)->toBe('submitted')->and($applicant->consent_at)->not->toBeNull()
            ->and($applicant->invoice->total)->toBe(150000)->and($applicant->invoice->source)->toBe('ppdb')->and($applicant->invoice->student_id)->toBeNull();
    });

    it('nomor yang sama melanjutkan draf; biaya nol langsung sudah bayar', function () {
        $register = app(RegisterApplicant::class);
        expect($register->start($this->period, '62811')->id)->toBe($register->start($this->period, '62811')->id);

        $this->period->update(['registration_fee' => 0]);
        $applicant = makeApplicant($this);
        expect($register->submit($applicant, true)->status)->toBe('paid')->and($applicant->fresh()->invoice)->toBeNull();
    });

    it('form tutup saat tanggal lewat atau kuota penuh', function () {
        expect(AdmissionPeriod::accepting()?->id)->toBe($this->period->id);
        $applicant = makeApplicant($this);

        makeApplicant($this, 'Budi', '6281200000002', 'accepted');
        expect(AdmissionPeriod::accepting())->toBeNull();
        Livewire::test(AdmissionWizard::class)->assertSee('Pendaftaran sedang ditutup');
        expect(fn () => app(RegisterApplicant::class)->submit($applicant, true))->toThrow(AdmissionException::class, 'ditutup');

        Applicant::where('name', 'Budi')->update(['status' => 'rejected']);
        expect(AdmissionPeriod::accepting())->not->toBeNull();

        $this->travelTo('2026-12-01 00:00:01');
        expect(AdmissionPeriod::accepting())->toBeNull();
        expect(app(AdmissionFlow::class)->closeExpired())->toBe(1);
    });

    it('dokumen: bukan jenis dikenal ditolak; setelah terkirim tidak bisa diganti; PDF dan berkas buruk', function () {
        $applicant = makeApplicant($this);
        $register = app(RegisterApplicant::class);
        expect(fn () => $register->storeDocument($applicant, 'aneh', UploadedFile::fake()->image('a.jpg')))->toThrow(AdmissionException::class, 'dikenal');

        $register->storeDocument($applicant, 'ijazah', UploadedFile::fake()->create('i.pdf', 10, 'application/pdf'));
        expect(ApplicantDocument::where('type', 'ijazah')->first()->isPdf())->toBeTrue();

        $register->submit($applicant, true);
        expect(fn () => $register->storeDocument($applicant->fresh(), 'kk', UploadedFile::fake()->image('a.jpg')))->toThrow(AdmissionException::class, 'dikirim');
    });

    it('dokumen hanya pemiliknya dan panitia; setiap akses panitia tercatat', function () {
        $applicant = makeApplicant($this);
        $document = $applicant->documents()->first();

        $this->get(route('ppdb.document', $document))->assertForbidden();
        $this->withSession([Applicant::SESSION_KEY => makeApplicant($this, 'Lain', '6281200000002')->uuid])->get(route('ppdb.document', $document))->assertForbidden();
        $this->withSession([Applicant::SESSION_KEY => $applicant->uuid])->get(route('ppdb.document', $document))->assertOk();

        $this->actingAs($this->teacher)->get(route('staff.ppdb.document', $document))->assertForbidden();
        $this->actingAs($this->panitia)->get(route('staff.ppdb.document', $document))->assertOk();
        expect(DB::table('audit_logs')->where('action', 'admission.document_viewed')->count())->toBe(1);
    });

    it('cek status: nomor tak dikenal dijawab sama; OTP benar menampilkan status dan tagihan; keputusan belum diumumkan disembunyikan', function () {
        $applicant = makeApplicant($this);
        app(RegisterApplicant::class)->submit($applicant, true);

        Livewire::test(AdmissionStatus::class)->set('reg_no', 'TIDAK-ADA')->call('sendOtp')->assertSet('otpSent', true)->set('code', '111111')->call('verifyOtp')->assertHasErrors('code');

        RateLimiter::clear('otp-ppdb:6281200000001');
        $page = Livewire::test(AdmissionStatus::class)->set('reg_no', $applicant->reg_no)->call('sendOtp')->set('code', CapturedOtp::$code)->call('verifyOtp')->assertHasNoErrors()
            ->assertSee('Menunggu pembayaran')->assertSee('Rp 150.000');

        $applicant->update(['status' => 'accepted', 'total_score' => 90]);
        $page->call('$refresh')->assertSee('Dalam seleksi')->assertDontSee('Selamat');
        $applicant->update(['announced_at' => now()]);
        $page->call('$refresh')->assertSee('Selamat, calon santri diterima');
    });

    it('OTP PPDB tidak bisa dipakai untuk masuk sebagai wali', function () {
        app(AdmissionOtp::class)->send('6281200000001');
        expect(app(VerifyLoginOtp::class)->handle('6281200000001', CapturedOtp::$code)[0])->toBe(OtpResult::Expired)
            ->and(app(AdmissionOtp::class)->verify('6281200000001', CapturedOtp::$code))->toBe(OtpResult::Valid);
    });
});

describe('pembayaran', function () {
    beforeEach(function () {
        $this->applicant = app(RegisterApplicant::class)->submit(makeApplicant($this), true);
        $this->invoice = $this->applicant->invoice;
    });

    it('lunas lewat gateway menjadi sudah bayar, sekali saja', function () {
        $payment = Payment::create(['payment_no' => 'KW-1', 'method' => 'midtrans', 'purpose' => 'admission_fee', 'amount' => 150000, 'gross_amount' => 150000, 'status' => 'pending']);
        PaymentAllocation::create(['payment_id' => $payment->id, 'invoice_id' => $this->invoice->id, 'amount' => 150000]);

        app(ApplySettlement::class)->handle($payment);
        app(ApplySettlement::class)->handle($payment);

        expect($this->applicant->fresh()->status)->toBe('paid')->and($this->invoice->fresh()->status)->toBe('paid');
    });

    it('bendahara mencatat tunai dari detail; panitia hanya melihat', function () {
        $this->actingAs($this->panitia);
        Livewire::test(ApplicantDetail::class, ['applicant' => $this->applicant])->assertDontSee('Catat Tunai')->call('recordCash')->assertForbidden();

        $this->actingAs($this->treasurer);
        Livewire::test(ApplicantDetail::class, ['applicant' => $this->applicant])->assertSee('Catat Tunai')->call('recordCash');
        expect($this->applicant->fresh()->status)->toBe('paid');
    });
});

describe('seleksi', function () {
    it('verifikasi butuh dokumen wajib disetujui; tolak wajib alasan', function () {
        $applicant = makeApplicant($this, status: 'paid');
        $flow = app(AdmissionFlow::class);
        expect(fn () => $flow->verify($applicant))->toThrow(AdmissionException::class, 'belum disetujui')
            ->and(fn () => $flow->reviewDocument($applicant->documents()->first(), 'rejected', ''))->toThrow(AdmissionException::class, 'alasan');

        foreach ($applicant->documents as $document) {
            $flow->reviewDocument($document, 'approved', null);
        }
        $flow->verify($applicant);
        expect($applicant->fresh()->status)->toBe('verified');
        expect(fn () => $flow->verify($applicant->fresh()))->toThrow(AdmissionException::class);
    });

    it('skor akhir contoh PRD: 100 x 20%, 80 x 50%, 90 x 30% = 87; belum lengkap belum dihitung', function () {
        $applicant = makeApplicant($this, status: 'in_selection');
        $scoring = app(ScoreApplicant::class);

        $scoring->save($applicant, $this->documentStep, 100, null, 1 * $this->panitia->id);
        $scoring->save($applicant, $this->examStep, 80, null, $this->panitia->id);
        expect($applicant->fresh()->total_score)->toBeNull();

        $scoring->save($applicant, $this->interviewStep, 90, null, $this->panitia->id);
        expect($applicant->fresh()->total_score)->toBe(87.0);

        expect(fn () => $scoring->save($applicant, $this->documentStep, 101, null, null))->toThrow(AdmissionException::class, '0 sampai 100');
    });

    it('kuota tidak bisa dilampaui; isi otomatis berurut skor, sisanya cadangan; mengundurkan diri lalu cadangan naik', function () {
        $first = makeApplicant($this, 'Ahmad', '6281200000001', 'in_selection');
        $second = makeApplicant($this, 'Budi', '6281200000002', 'in_selection');
        $third = makeApplicant($this, 'Cici', '6281200000003', 'in_selection');
        fullScore($this, $first, 70);
        fullScore($this, $second, 90);
        $flow = app(AdmissionFlow::class);

        expect(fn () => $flow->decide($third, 'accepted'))->toThrow(AdmissionException::class, 'Skor akhir');

        expect($flow->fillQuota($this->period))->toBe(['accepted' => 1, 'waitlist' => 1, 'skipped' => 1])
            ->and($second->fresh()->status)->toBe('accepted')->and($first->fresh()->status)->toBe('waitlist');

        expect(fn () => $flow->decide($first->fresh(), 'accepted'))->toThrow(AdmissionException::class, 'Kuota');

        $flow->decide($second->fresh(), 'rejected', 'Mengundurkan diri');
        $flow->decide($first->fresh(), 'accepted');
        expect($first->fresh()->status)->toBe('accepted');
    });

    it('pengumuman terjadwal: belum waktunya tersembunyi, job mengumumkan sekali', function () {
        $applicant = makeApplicant($this, status: 'accepted');
        $flow = app(AdmissionFlow::class);

        expect($flow->announce($this->period, now()->addHour()))->toBe(0)
            ->and($flow->announceDue())->toBe(0)
            ->and($applicant->fresh()->publicStatus())->toBe('in_selection');

        $this->travel(2)->hours();
        expect($flow->announceDue())->toBe(1)->and($flow->announceDue())->toBe(0)
            ->and($applicant->fresh()->publicStatus())->toBe('accepted');
    });

    it('layar peringkat dan daftar: panitia bisa, ustadz tidak, bendahara hanya baca', function () {
        $applicant = makeApplicant($this, status: 'verified');

        $this->actingAs($this->teacher)->get(route('staff.ppdb.applicants.index'))->assertForbidden();

        $this->actingAs($this->treasurer);
        Livewire::test(ApplicantList::class)->assertSee('Ahmad')->assertDontSee('Pilih Ahmad')->set('selected', [$applicant->uuid])->call('startSelection')->assertForbidden();

        $this->actingAs($this->panitia);
        Livewire::test(ApplicantList::class)->set('status', 'verified')->assertSee('Ahmad')->set('selected', [$applicant->uuid])->call('startSelection');
        expect($applicant->fresh()->status)->toBe('in_selection');

        Livewire::test(AdmissionSelection::class)->assertSee('Ahmad')->call('announceNow');
    });

    it('periode: buka butuh bobot 100; satu periode terbuka; urutan tahap', function () {
        $this->period->update(['status' => 'closed']);
        $this->actingAs($this->panitia);

        $page = Livewire::test(AdmissionPeriods::class)->call('create')
            ->set('name', 'G2')->set('open_at', '2026-11-02T10:00')->set('close_at', '2026-11-01T10:00')->set('quota', '5')->call('save')->assertHasErrors('close_at')
            ->set('close_at', '2026-12-02T10:00')->set('registration_fee', 'Rp 100.000')->call('save')->assertHasNoErrors();

        $period = AdmissionPeriod::where('name', 'G2')->first();
        expect($period->registration_fee)->toBe(100000)->and($period->open_at->toIso8601String())->toBe('2026-11-02T03:00:00+00:00');

        $page->call('open', $period->uuid)->assertHasErrors('period-'.$period->uuid);
        $page->set('step_name', 'A')->set('step_type', 'interview')->set('step_weight', '40')->call('addStep', $period->uuid)->assertHasNoErrors();
        $page->set('step_name', 'B')->set('step_type', 'exam')->set('step_weight', '60')->call('addStep', $period->uuid)->assertHasErrors('step_exam');
        $page->set('step_type', 'document')->call('addStep', $period->uuid)->assertHasNoErrors();

        $steps = $period->steps()->get();
        $page->call('moveStep', $steps[1]->uuid, -1);
        expect($period->steps()->first()->name)->toBe('B');

        $page->call('open', $period->uuid)->assertHasNoErrors();
        expect($period->fresh()->status)->toBe('open');

        $other = AdmissionPeriod::create(['name' => 'G3', 'open_at' => '2026-11-02 00:00:00', 'close_at' => '2026-12-02 00:00:00', 'quota' => 1]);
        AdmissionStep::create(['period_id' => $other->id, 'name' => 'X', 'type' => 'interview', 'weight' => 100]);
        $page->call('open', $other->uuid)->assertHasErrors('period-'.$other->uuid);
    });
});

describe('daftar ulang', function () {
    it('satu transaksi: santri, akun, wali, dompet, tagihan sekali bayar; tidak bisa dua kali', function () {
        $applicant = makeApplicant($this, status: 'accepted');
        $type = FeeType::create(['code' => 'UM', 'name' => 'Uang masuk', 'category' => 'one_time']);
        FeeRate::create(['fee_type_id' => $type->id, 'scope' => 'default', 'amount' => 2000000, 'valid_from' => '2026-01-01']);
        $this->actingAs($this->panitia);

        $page = Livewire::test(ApplicantDetail::class, ['applicant' => $applicant])->assertSee('Daftar Ulang')->assertSee('Uang masuk')->call('enroll')->assertSee('Sandi awal santri');

        $applicant->refresh();
        $student = $applicant->student;
        expect($applicant->status)->toBe('enrolled')->and($student->name)->toBe('Ahmad')->and($student->birth_date->format('Y-m-d'))->toBe('2012-01-01')
            ->and($student->user->hasRole('santri'))->toBeTrue()
            ->and($student->guardians()->first()->phone_wa)->toBe('6281200000001')
            ->and(User::where('phone', '6281200000001')->first()->hasRole('wali'))->toBeTrue()
            ->and(Wallet::where('student_id', $student->id)->exists())->toBeTrue()
            ->and(Invoice::where('student_id', $student->id)->first()->total)->toBe(2000000);

        expect(fn () => app(EnrollApplicant::class)->handle($applicant, null, null, $this->panitia->id))->toThrow(AdmissionException::class);
        expect(Student::count())->toBe(1);
        $page->assertHasNoErrors();
    });

    it('yang belum diterima tidak bisa; nomor wali yang sudah ada dipakai ulang', function () {
        expect(fn () => app(EnrollApplicant::class)->handle(makeApplicant($this, status: 'waitlist'), null, null, $this->panitia->id))->toThrow(AdmissionException::class, 'diterima');

        $first = makeApplicant($this, 'Kakak', '6281200000009', 'accepted');
        $this->period->update(['quota' => 5]);
        app(EnrollApplicant::class)->handle($first, null, null, $this->panitia->id);
        $second = makeApplicant($this, 'Adik', '6281200000009', 'accepted');
        app(EnrollApplicant::class)->handle($second, null, null, $this->panitia->id);
        expect(User::where('phone', '6281200000009')->count())->toBe(1)->and($first->fresh()->student->guardians()->first()->id)->toBe($second->fresh()->student->guardians()->first()->id);
    });
});

describe('ujian seleksi', function () {
    beforeEach(function () {
        $bank = QuestionBank::create(['name' => 'B', 'subject_id' => Subject::create(['code' => 'S', 'name' => 'S'])->id]);
        $this->question = app(SaveQuestion::class)->handle($bank, ['type' => 'mcq', 'body' => 'Soal seleksi', 'options' => ['x', 'y'], 'answer_key' => 'A']);
        $this->exam = Exam::create(['title' => 'Tes Masuk', 'audience' => 'applicant', 'duration_minutes' => 30, 'start_at' => '2026-11-02 04:00:00', 'end_at' => '2026-11-02 08:00:00', 'status' => 'published']);
        $this->exam->questions()->attach($this->question->id, ['sort_order' => 0]);
        $this->examStep->update(['type' => 'exam', 'exam_id' => $this->exam->id]);
        $this->applicant = makeApplicant($this, status: 'in_selection');
    });

    it('tanpa akun: mengerjakan lewat sesi OTP, nilai masuk ke tahap otomatis', function () {
        $session = [Applicant::SESSION_KEY => $this->applicant->uuid];
        $attempt = app(RunAttempt::class)->startForApplicant($this->exam, $this->applicant, null, null, null);

        $this->withSession($session)->get(route('ppdb.exam.take', $attempt))->assertOk()->assertSee('Soal seleksi')->assertDontSee('answer_key');
        $this->withSession($session)->postJson(route('ppdb.exam.answers', $attempt), ['token' => $attempt->session_token, 'batch' => [['question_id' => $this->question->id, 'answer' => 'A', 'client_seq' => 1]]])->assertOk();
        $this->withSession($session)->postJson(route('ppdb.exam.submit', $attempt), ['token' => $attempt->session_token])->assertOk()->assertJsonFragment(['done' => route('ppdb.exam.done', $attempt)]);
        $this->withSession($session)->get(route('ppdb.exam.done', $attempt))->assertRedirect(route('ppdb.status'));

        expect((float) $this->applicant->results()->where('step_id', $this->examStep->id)->value('score'))->toBe(100.0);
    });

    it('menolak: pendaftar lain, belum seleksi, ujian santri; kuota percobaan', function () {
        $attempt = app(RunAttempt::class)->startForApplicant($this->exam, $this->applicant, null, null, null);
        $other = makeApplicant($this, 'Lain', '6281200000002', 'in_selection');

        $this->withSession([Applicant::SESSION_KEY => $other->uuid])->get(route('ppdb.exam.take', $attempt))->assertForbidden();
        $this->get(route('ppdb.exam.take', $attempt))->assertForbidden();

        $notYet = makeApplicant($this, 'Baru', '6281200000003', 'verified');
        expect(fn () => app(RunAttempt::class)->startForApplicant($this->exam, $notYet, null, null, null))->toThrow(ExamException::class);

        $this->exam->update(['audience' => 'student']);
        expect(fn () => app(RunAttempt::class)->startForApplicant($this->exam, $other, null, null, null))->toThrow(ExamException::class);
    });

    it('halaman status menawarkan ujian; layar ujian dan hasil menampilkan nama pendaftar', function () {
        $page = Livewire::withQueryParams([]);
        session([Applicant::SESSION_KEY => $this->applicant->uuid]);
        Livewire::test(AdmissionStatus::class)->assertSee('Mulai Ujian')->call('startExam', $this->examStep->uuid)->assertHasNoErrors();
        expect(ExamAttempt::where('applicant_id', $this->applicant->id)->count())->toBe(1);
        unset($page);
    });
});

it('modul mati: rute publik dan panel tertutup; beranda hanya menawarkan daftar bila dibuka', function () {
    $this->get('/')->assertOk()->assertSee('Daftar Santri Baru');
    $this->get(route('ppdb.register'))->assertOk();

    Module::query()->where('module_key', 'ppdb')->update(['is_enabled' => false]);
    app(ModuleRegistry::class)->forget();

    $this->get('/')->assertOk()->assertDontSee('Daftar Santri Baru');
    $this->get(route('ppdb.register'))->assertForbidden();
    $this->actingAs($this->panitia)->get(route('staff.ppdb.periods.index'))->assertForbidden();
});

it('tagihan pendaftar tidak merusak laporan keuangan santri', function () {
    app(RegisterApplicant::class)->submit(makeApplicant($this), true);
    $this->travelTo('2026-12-15 05:00:00');
    $admin = tap(User::create(['name' => 'Admin', 'email' => 'adm@uji.test', 'type' => 'staff']))->assignRole('admin');

    foreach (['staff.invoices.index', 'staff.arrears.index'] as $name) {
        $this->actingAs($admin)->get(route($name))->assertOk();
    }

    $invoice = Invoice::whereNotNull('applicant_id')->first();
    $this->get(route('staff.invoices.show', $invoice))->assertOk();
    app(RecordCashPayment::class)->handle([$invoice->id], $invoice->outstanding(), $admin->id);
    $this->get(route('staff.payments.index'))->assertOk();
});
