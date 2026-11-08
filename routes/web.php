<?php

use App\Http\Middleware\EnsurePreviewAllowed;
use App\Modules\Academic\Actions\BuildReportCardPdf;
use App\Modules\Academic\Http\AttendanceCameraController;
use App\Modules\Academic\Http\PhaseDocuments;
use App\Modules\Academic\Livewire\AcademicMasters;
use App\Modules\Academic\Livewire\AttendanceRecap;
use App\Modules\Academic\Livewire\AttendanceReview;
use App\Modules\Academic\Livewire\AttendanceSheet;
use App\Modules\Academic\Livewire\AttendanceToday;
use App\Modules\Academic\Livewire\CurriculumBoard;
use App\Modules\Academic\Livewire\FaceEnrollments;
use App\Modules\Academic\Livewire\GradeBook;
use App\Modules\Academic\Livewire\GuardianFaceConsent;
use App\Modules\Academic\Livewire\ReportCards;
use App\Modules\Academic\Livewire\ScheduleBuilder;
use App\Modules\Academic\Livewire\StudentAcademics;
use App\Modules\Academic\Livewire\TeacherHome;
use App\Modules\Academic\Models\ReportCard;
use App\Modules\Academic\Support\AttendanceStats;
use App\Modules\Admission\Http\ApplicantFileController;
use App\Modules\Admission\Livewire\AdmissionPeriods;
use App\Modules\Admission\Livewire\AdmissionSelection;
use App\Modules\Admission\Livewire\AdmissionStatus;
use App\Modules\Admission\Livewire\AdmissionWizard;
use App\Modules\Admission\Livewire\ApplicantDetail;
use App\Modules\Admission\Livewire\ApplicantList;
use App\Modules\Affairs\Livewire\AlumniDirectory;
use App\Modules\Affairs\Livewire\DisciplineBoard;
use App\Modules\Affairs\Livewire\DutyBoard;
use App\Modules\Affairs\Livewire\GovernmentExport;
use App\Modules\Affairs\Livewire\HealthDesk;
use App\Modules\Affairs\Livewire\InventoryBoard;
use App\Modules\Affairs\Livewire\RiskBoard;
use App\Modules\Affairs\Models\AlumniProfile;
use App\Modules\Core\Actions\RecordAudit;
use App\Modules\Core\Exports\ImportErrorsExport;
use App\Modules\Core\Exports\ImportTemplateExport;
use App\Modules\Core\Exports\StudentsExport;
use App\Modules\Core\Livewire\Announcements\AnnouncementIndex;
use App\Modules\Core\Livewire\Auth\ForgotPassword;
use App\Modules\Core\Livewire\Auth\Login;
use App\Modules\Core\Livewire\Auth\ResetPassword;
use App\Modules\Core\Livewire\Auth\TwoFactorChallenge;
use App\Modules\Core\Livewire\Auth\VerifyOtp;
use App\Modules\Core\Livewire\Guardians\GuardianIndex;
use App\Modules\Core\Livewire\Masters\MasterData;
use App\Modules\Core\Livewire\OrgStructure;
use App\Modules\Core\Livewire\Settings\AuditLogs;
use App\Modules\Core\Livewire\Settings\IntegrationSettings;
use App\Modules\Core\Livewire\Settings\ModuleSettings;
use App\Modules\Core\Livewire\Settings\ProfileSettings;
use App\Modules\Core\Livewire\Settings\SystemHealth;
use App\Modules\Core\Livewire\Staff\AccountSecurity;
use App\Modules\Core\Livewire\Staff\Dashboard;
use App\Modules\Core\Livewire\Students\ClassPromotion;
use App\Modules\Core\Livewire\Students\CustomFields;
use App\Modules\Core\Livewire\Students\StudentForm;
use App\Modules\Core\Livewire\Students\StudentImport;
use App\Modules\Core\Livewire\Students\StudentIndex;
use App\Modules\Core\Livewire\Students\StudentShow;
use App\Modules\Core\Livewire\Users\UserIndex;
use App\Modules\Core\Models\Classroom;
use App\Modules\Core\Models\Guardian;
use App\Modules\Core\Models\Import;
use App\Modules\Core\Models\OrgPosition;
use App\Modules\Core\Models\SchoolProfile;
use App\Modules\Core\Models\Student;
use App\Modules\Core\Models\Student as AffairsStudent;
use App\Modules\Exam\Http\AttemptController;
use App\Modules\Exam\Livewire\ExamList;
use App\Modules\Exam\Livewire\ExamMonitor;
use App\Modules\Exam\Livewire\ExamPrepare;
use App\Modules\Exam\Livewire\ExamResults;
use App\Modules\Exam\Livewire\ExamWizard;
use App\Modules\Exam\Livewire\QuestionBanks;
use App\Modules\Exam\Livewire\StudentExams;
use App\Modules\Exam\Models\Exam;
use App\Modules\Exam\Models\ExamAttempt;
use App\Modules\Finance\Actions\BuildReceiptPdf;
use App\Modules\Finance\Actions\FinanceException;
use App\Modules\Finance\Exports\ArrayExport;
use App\Modules\Finance\Exports\InvoicesExport;
use App\Modules\Finance\Livewire\Invoices\GenerateScreen;
use App\Modules\Finance\Livewire\Invoices\InvoiceCreate;
use App\Modules\Finance\Livewire\Invoices\InvoiceIndex;
use App\Modules\Finance\Livewire\Invoices\InvoiceShow;
use App\Modules\Finance\Livewire\Payments\CashPayment;
use App\Modules\Finance\Livewire\Payments\PaymentIndex;
use App\Modules\Finance\Livewire\Payments\PaymentShow;
use App\Modules\Finance\Livewire\Payments\TransferReview;
use App\Modules\Finance\Livewire\Portal\GuardianCheckout;
use App\Modules\Finance\Livewire\Portal\GuardianInvoices;
use App\Modules\Finance\Livewire\Portal\GuardianInvoiceShow;
use App\Modules\Finance\Livewire\Portal\GuardianPayment;
use App\Modules\Finance\Livewire\Reports\ArrearsImport;
use App\Modules\Finance\Livewire\Reports\ArrearsReport;
use App\Modules\Finance\Livewire\Reports\CashReport;
use App\Modules\Finance\Livewire\Settings\FeeTypes;
use App\Modules\Finance\Models\CashTransaction;
use App\Modules\Finance\Models\Payment;
use App\Modules\Finance\Support\ArrearsQuery;
use App\Modules\Leave\Livewire\GateBoard;
use App\Modules\Leave\Livewire\GateLogs;
use App\Modules\Leave\Livewire\GateScanner;
use App\Modules\Leave\Livewire\GateShiftScreen;
use App\Modules\Leave\Livewire\GateToday;
use App\Modules\Leave\Livewire\GuardianLeaves;
use App\Modules\Leave\Livewire\LeaveDesk;
use App\Modules\Leave\Models\GateLog;
use App\Modules\Letters\Livewire\LetterCenter;
use App\Modules\Letters\Models\Letter;
use App\Modules\Letters\Models\Letterhead;
use App\Modules\Letters\Support\LetterSettings;
use App\Modules\Masjid\Http\DisplayController;
use App\Modules\Masjid\Http\PublicMasjidController;
use App\Modules\Masjid\Livewire\DisplayContents;
use App\Modules\Masjid\Livewire\DisplayScreens;
use App\Modules\Masjid\Livewire\GuardianDisplayConsent;
use App\Modules\Masjid\Livewire\MasjidSettings;
use App\Modules\Notification\Http\FonnteWebhookController;
use App\Modules\Notification\Livewire\GuardianMore;
use App\Modules\Notification\Livewire\Templates;
use App\Modules\Payment\Http\MidtransWebhookController;
use App\Modules\Payment\Livewire\Reconciliation;
use App\Modules\Pos\Livewire\Cashier;
use App\Modules\Pos\Livewire\CatalogImport;
use App\Modules\Pos\Livewire\MerchantAdmin;
use App\Modules\Pos\Livewire\OrderHistory;
use App\Modules\Pos\Livewire\PayoutQueue;
use App\Modules\Pos\Livewire\ProductCatalog;
use App\Modules\Pos\Livewire\Promotions;
use App\Modules\Pos\Livewire\Purchasing;
use App\Modules\Pos\Livewire\SalesReport;
use App\Modules\Pos\Livewire\StockScreen;
use App\Modules\Pos\Models\Merchant;
use App\Modules\Pos\Models\MerchantProduct;
use App\Modules\Pos\Models\PosOrder;
use App\Modules\Pos\Support\SalesStats;
use App\Modules\Tahfidz\Livewire\MutabaahVerify;
use App\Modules\Tahfidz\Livewire\SetoranForm;
use App\Modules\Tahfidz\Livewire\StudentTahfidz;
use App\Modules\Tahfidz\Livewire\TahfidzAdmin;
use App\Modules\Tahfidz\Models\QuranSurah;
use App\Modules\Tahfidz\Models\TahfidzRecord;
use App\Modules\Tahfidz\Support\TahfidzProgress;
use App\Modules\Tahfidz\Support\TahfidzRecap;
use App\Modules\Wallet\Actions\BuildCardsPdf;
use App\Modules\Wallet\Livewire\CardAdmin;
use App\Modules\Wallet\Livewire\DeviceAdmin;
use App\Modules\Wallet\Livewire\GuardianSpendingRules;
use App\Modules\Wallet\Livewire\GuardianTopup;
use App\Modules\Wallet\Livewire\GuardianWallet;
use App\Modules\Wallet\Livewire\ScanLog;
use App\Modules\Wallet\Livewire\StudentCardPage;
use App\Modules\Wallet\Livewire\WalletAdmin;
use App\Modules\Wallet\Models\CardScan;
use App\Modules\Wallet\Models\Device;
use App\Modules\Wallet\Models\StudentCard;
use App\Support\Pdf\PdfRenderer;
use App\Support\SchoolIdentity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;

Route::view('/', 'home')->name('home');

Route::post('/webhooks/fonnte/{secret}', FonnteWebhookController::class)->middleware('throttle:240,1')->name('webhooks.fonnte');
Route::post('/webhooks/midtrans', MidtransWebhookController::class)->middleware('throttle:120,1')->name('webhooks.midtrans');

Route::middleware('module:ppdb')->prefix('ppdb')->name('ppdb.')->group(function (): void {
    Route::get('/daftar', AdmissionWizard::class)->name('register');
    Route::get('/status', AdmissionStatus::class)->name('status');
    Route::get('/dokumen/{document}', [ApplicantFileController::class, 'own'])->name('document');
    Route::get('/ujian/{attempt}', [AttemptController::class, 'show'])->name('exam.take');
    Route::get('/ujian/{attempt}/selesai', [AttemptController::class, 'done'])->name('exam.done');
    Route::post('/ujian/{attempt}/jawaban', [AttemptController::class, 'answers'])->middleware('throttle:240,1')->name('exam.answers');
    Route::post('/ujian/{attempt}/kumpulkan', [AttemptController::class, 'submit'])->name('exam.submit');
    Route::post('/ujian/{attempt}/peristiwa', [AttemptController::class, 'events'])->middleware('throttle:240,1')->name('exam.events');
    Route::get('/ujian-media/{question}', [AttemptController::class, 'media'])->name('exam.media');
});

Route::get('/display', [DisplayController::class, 'page'])->middleware('module:masjid_display')->name('display');
Route::middleware('module:masjid_public')->group(function (): void {
    Route::get('/masjid', [PublicMasjidController::class, 'today'])->name('masjid.public');
    Route::get('/masjid/jadwal.pdf', [PublicMasjidController::class, 'monthPdf'])->name('masjid.public.month');
});
Route::get('/panel-masjid/media/{content}', [DisplayController::class, 'staffMedia'])->middleware(['auth', 'can:masjid.content'])->name('display.media.staff');

Route::middleware('guest')->group(function (): void {
    Route::get('/masuk', Login::class)->name('login');
    Route::get('/masuk/otp', VerifyOtp::class)->name('login.otp');
    Route::get('/masuk/dua-langkah', TwoFactorChallenge::class)->name('login.two-factor');
    Route::get('/lupa-sandi', ForgotPassword::class)->name('password.request');
    Route::get('/reset-sandi/{token}', ResetPassword::class)->name('password.reset');
});

Route::post('/keluar', function (Request $request, RecordAudit $audit) {
    $audit->handle('logout', 'User', $request->user()->id);
    Auth::logout();
    $request->session()->invalidate();
    $request->session()->regenerateToken();

    return redirect()->route('login');
})->middleware('auth')->name('logout');

Route::middleware(['auth', 'user.type:staff', 'can:panel.access'])->prefix('panel')->name('staff.')->group(function (): void {
    Route::get('/', Dashboard::class)->name('dashboard');
    Route::get('/akun', AccountSecurity::class)->name('account');

    Route::middleware('module:finance')->group(function (): void {
        Route::middleware('can:finance.view')->group(function (): void {
            Route::get('/tagihan', InvoiceIndex::class)->name('invoices.index');
            Route::get('/tagihan/ekspor', fn (Request $request) => Excel::download(new InvoicesExport($request->only(['status', 'kelas', 'periode'])), 'tagihan.xlsx'))->name('invoices.export');
            Route::get('/pembayaran', PaymentIndex::class)->name('payments.index');
            Route::get('/tunggakan', ArrearsReport::class)->name('arrears.index');
            Route::get('/tunggakan/ekspor', function (Request $request) {
                $rows = ArrearsQuery::rows($request->filled('kelas') ? $request->integer('kelas') : null)
                    ->map(fn (array $row) => [$row['student']->nis, $row['student']->name, $row['classroom'], $row['invoices'], $row['outstanding'], $row['days']])
                    ->all();

                return Excel::download(new ArrayExport(['NIS', 'Santri', 'Kelas', 'Jumlah tagihan', 'Tunggakan', 'Lama (hari)'], $rows), 'tunggakan.xlsx');
            })->name('arrears.export');
            Route::get('/kas', CashReport::class)->name('cash.index');
            Route::get('/kas/ekspor', function (Request $request) {
                $rows = CashTransaction::query()->with('category:id,name')
                    ->when($request->filled('dari'), fn ($query) => $query->whereDate('transaction_date', '>=', $request->date('dari')))
                    ->when($request->filled('sampai'), fn ($query) => $query->whereDate('transaction_date', '<=', $request->date('sampai')))
                    ->latest('transaction_date')->get()
                    ->map(fn (CashTransaction $cash) => [$cash->transaction_date->toDateString(), $cash->type === 'in' ? 'Masuk' : 'Keluar', $cash->category->name, $cash->description, $cash->amount])
                    ->all();

                return Excel::download(new ArrayExport(['Tanggal', 'Jenis', 'Kategori', 'Keterangan', 'Nominal'], $rows), 'kas.xlsx');
            })->name('cash.export');
        });

        Route::middleware('can:finance.manage')->group(function (): void {
            Route::get('/tagihan/generate', GenerateScreen::class)->name('invoices.generate');
            Route::get('/tagihan/manual', InvoiceCreate::class)->name('invoices.create');
            Route::get('/pembayaran/tunai', CashPayment::class)->name('payments.cash');
            Route::get('/tunggakan/impor', ArrearsImport::class)->name('arrears.import');
            Route::get('/tunggakan/impor/template', fn () => Excel::download(new ArrayExport(ArrearsImport::HEADINGS, []), 'template-tunggakan-awal.xlsx'))->name('arrears.import.template');
            Route::get('/pengaturan/jenis-tagihan', FeeTypes::class)->name('settings.fee-types');
        });

        Route::get('/tagihan/{invoice}', InvoiceShow::class)->middleware('can:view,invoice')->name('invoices.show');
        Route::get('/pembayaran/{payment}', PaymentShow::class)->middleware('can:view,payment')->name('payments.show');
        Route::get('/pembayaran/{payment}/bukti', function (Payment $payment) {
            abort_unless($payment->proof_path && Storage::disk('local')->exists($payment->proof_path), 404);

            return Storage::disk('local')->response($payment->proof_path);
        })->middleware('can:finance.view')->name('payments.proof');
        Route::get('/rekonsiliasi', Reconciliation::class)->middleware(['module:payment_midtrans', 'can:finance.view'])->name('reconciliation');
        Route::get('/verifikasi-transfer', TransferReview::class)->middleware(['module:payment_manual', 'can:finance.verify'])->name('transfers.index');
    });

    Route::middleware(['module:academic', 'can:academic.manage'])->group(function (): void {
        Route::get('/mapel', AcademicMasters::class)->name('subjects.index');
        Route::get('/jadwal', ScheduleBuilder::class)->name('schedules.index');
    });

    Route::middleware('module:attendance')->group(function (): void {
        Route::get('/absensi', AttendanceToday::class)->middleware('can:academic.manage')->name('attendance.index');
        Route::get('/absensi/rekap', AttendanceRecap::class)->middleware('can:academic.manage')->name('attendance.recap');
        Route::get('/absensi/rekap/ekspor', function (Request $request) {
            $rows = AttendanceStats::perStudent($request->filled('kelas') ? $request->integer('kelas') : null, null, AttendanceRecap::date($request->query('dari')) ?? now()->startOfMonth()->toDateString(), AttendanceRecap::date($request->query('sampai')) ?? now()->toDateString())
                ->map(fn (array $row) => [$row['student']->nis, $row['student']->name, $row['hadir'], $row['terlambat'], $row['izin'], $row['sakit'], $row['alpa'], $row['percent']])->all();

            return Excel::download(new ArrayExport(['NIS', 'Santri', 'Hadir', 'Terlambat', 'Izin', 'Sakit', 'Alpa', 'Kehadiran (%)'], $rows), 'rekap-absensi.xlsx');
        })->middleware('can:academic.manage')->name('attendance.export');
        Route::get('/absensi/rekap/pdf', function (Request $request, PdfRenderer $pdf) {
            $from = AttendanceRecap::date($request->query('dari')) ?? now()->startOfMonth()->toDateString();
            $to = AttendanceRecap::date($request->query('sampai')) ?? now()->toDateString();
            $classroom = $request->filled('kelas') ? Classroom::query()->find($request->integer('kelas')) : null;
            $rows = AttendanceStats::perStudent($classroom?->id, null, $from, $to);

            return response($pdf->render(view('academic.pdf.attendance', ['rows' => $rows, 'from' => $from, 'to' => $to, 'classroom' => $classroom?->name])->render()), 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'attachment; filename="rekap-absensi.pdf"']);
        })->middleware('can:academic.manage')->name('attendance.pdf');
        Route::get('/absensi/sesi/{session}', AttendanceSheet::class)->name('attendance.session');
        Route::get('/absensi/tinjauan', AttendanceReview::class)->middleware('can:academic.manage')->name('attendance.review');
        Route::get('/wajah', FaceEnrollments::class)->middleware(['module:face_attendance', 'can:face.enroll'])->name('face');
    });

    Route::middleware('can:masters.manage')->group(function (): void {
        Route::get('/struktur', OrgStructure::class)->name('org.index');
        Route::get('/struktur/foto/{position}', function (OrgPosition $position) {
            abort_unless($position->photo_path && Storage::disk('local')->exists($position->photo_path), 404);

            return Storage::disk('local')->response($position->photo_path);
        })->name('org.photo');
    });

    Route::prefix('kurikulum')->name('curriculum.')->middleware(['module:curriculum', 'can:academic.manage'])->group(function (): void {
        Route::get('/', CurriculumBoard::class)->name('index');
        Route::get('/desain/{design}', [PhaseDocuments::class, 'preview'])->name('design');
        Route::get('/logo/{index}', [PhaseDocuments::class, 'logo'])->whereNumber('index')->name('logo');
        Route::get('/{classroom}/rapor', [PhaseDocuments::class, 'report'])->name('report');
        Route::get('/{classroom}/sertifikat', [PhaseDocuments::class, 'certificate'])->name('certificate');
    });

    Route::middleware('module:grading')->group(function (): void {
        Route::get('/nilai', GradeBook::class)->middleware('can:grades.manage')->name('grades.index');
        Route::get('/rapor', ReportCards::class)->middleware('can:reports.publish')->name('report-cards.index');
    });

    Route::get('/perizinan', LeaveDesk::class)->middleware(['module:leave', 'can:leave.manage'])->name('leaves.index');

    Route::prefix('gerbang')->name('gate.')->middleware(['module:gate', 'can:gate.operate'])->group(function (): void {
        Route::get('/', GateBoard::class)->name('index');
        Route::get('/pindai', GateScanner::class)->name('scan');
        Route::get('/izin-hari-ini', GateToday::class)->name('today');
        Route::get('/riwayat', GateLogs::class)->name('logs');
        Route::get('/riwayat/ekspor', function (Request $request) {
            $rows = GateLogs::query($request->query('tanggal'), $request->query('hasil'))->limit(10000)->get()
                ->map(fn (GateLog $log) => [
                    $log->occurred_at->setTimezone(config('sanad.display_timezone'))->format('Y-m-d H:i:s'),
                    $log->student?->nis,
                    $log->student?->name,
                    $log->direction === 'out' ? 'Keluar' : 'Masuk',
                    GateLog::METHODS[$log->method],
                    GateLog::RESULTS[$log->result],
                    $log->deny_reason ?? $log->note,
                    $log->pickup_name,
                    $log->recorder?->name,
                ])->all();

            return Excel::download(new ArrayExport(['Waktu', 'NIS', 'Santri', 'Arah', 'Cara', 'Hasil', 'Catatan', 'Penjemput', 'Petugas'], $rows), 'riwayat-gerbang.xlsx');
        })->name('logs.export');
        Route::get('/shift', GateShiftScreen::class)->name('shift');
        Route::get('/foto/{student}', function (Student $student) {
            abort_unless($student->photo_path && Storage::disk('local')->exists($student->photo_path), 404);

            return Storage::disk('local')->response($student->photo_path);
        })->name('photo');
    });

    Route::prefix('tahfidz')->name('tahfidz.')->middleware('module:tahfidz')->group(function (): void {
        Route::get('/', SetoranForm::class)->middleware('can:tahfidz.input')->name('index');
        Route::get('/mutabaah', MutabaahVerify::class)->middleware('can:tahfidz.input')->name('mutabaah');
        Route::middleware('can:tahfidz.manage')->group(function (): void {
            Route::get('/kelola', TahfidzAdmin::class)->name('admin');
            Route::get('/rekap/ekspor', function (Request $request) {
                $from = TahfidzRecap::date($request->query('dari')) ?? now()->startOfMonth()->toDateString();
                $to = TahfidzRecap::date($request->query('sampai')) ?? now()->toDateString();
                $rows = TahfidzRecap::rows($request->filled('kelas') ? $request->integer('kelas') : null, $from, $to)
                    ->map(fn (array $row) => [$row['student']->nis, $row['student']->name, $row['ziyadah_pages'], $row['murojaah'], $row['tahsin'], $row['mutabaah']])->all();

                return Excel::download(new ArrayExport(['NIS', 'Santri', 'Ziyadah (halaman)', 'Murojaah (kali)', 'Tahsin (kali)', 'Amalan selesai'], $rows), 'rekap-tahfidz.xlsx');
            })->name('export');
            Route::get('/ringkasan/{student}', function (Student $student, PdfRenderer $pdf) {
                return response($pdf->render(view('tahfidz.pdf.summary', [
                    'student' => $student->load('classroom'),
                    'juzMap' => TahfidzProgress::juzMap($student),
                    'memorized' => QuranSurah::query()->whereIn('number', TahfidzProgress::memorizedSurahs($student))->orderBy('number')->get(),
                    'history' => TahfidzRecord::query()->with(['surahFrom', 'surahTo'])->where('student_id', $student->id)->latest('record_date')->limit(30)->get(),
                ])->render()), 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'attachment; filename="ringkasan-hafalan-'.$student->nis.'.pdf"']);
            })->name('summary');
        });
    });

    Route::prefix('ujian')->name('exams.')->middleware(['module:exam', 'can:exams.manage'])->group(function (): void {
        Route::get('/', ExamList::class)->name('index');
        Route::get('/bank-soal', QuestionBanks::class)->name('banks');
        Route::get('/{exam}/susun', ExamWizard::class)->name('edit');
        Route::get('/{exam}/monitor', ExamMonitor::class)->name('monitor');
        Route::get('/{exam}/hasil', ExamResults::class)->name('results');
    });

    Route::middleware(['module:masjid_prayer', 'can:masjid.access'])->group(function (): void {
        Route::get('/masjid', MasjidSettings::class)->name('masjid.index');
        Route::get('/masjid/jadwal.pdf', [PublicMasjidController::class, 'monthPdf'])->name('masjid.month-pdf');
    });
    Route::middleware('module:masjid_display')->group(function (): void {
        Route::get('/masjid/layar', DisplayScreens::class)->middleware('can:masjid.screens')->name('masjid.screens');
        Route::get('/masjid/layar/{screen}/pratinjau', [DisplayController::class, 'preview'])->middleware('can:masjid.screens')->name('masjid.preview');
        Route::get('/masjid/konten', DisplayContents::class)->middleware('can:masjid.content')->name('masjid.contents');
    });
    Route::get('/pelanggaran', DisciplineBoard::class)->middleware('module:discipline')->middleware('can:discipline.access')->name('discipline.index');
    Route::get('/kesehatan', HealthDesk::class)->middleware('module:health')->name('health.index');
    Route::get('/analitik', RiskBoard::class)->middleware(['module:analytics', 'can:analytics.view'])->name('analytics.index');
    Route::middleware(['module:alumni', 'can:alumni.manage'])->group(function (): void {
        Route::get('/alumni', AlumniDirectory::class)->name('alumni.index');
        Route::get('/alumni/ekspor', function () {
            $rows = AlumniProfile::query()->with('student:id,name,nis')->whereHas('student', fn ($query) => $query->where('status', 'graduated'))->get()
                ->map(fn ($profile) => [$profile->student->nis, $profile->student->name, $profile->graduated_on?->toDateString(), $profile->current_activity, $profile->contact_phone, $profile->contact_email])->all();

            return Excel::download(new ArrayExport(['NIS', 'Nama', 'Tanggal lulus', 'Kegiatan', 'Telepon', 'Email'], $rows), 'alumni.xlsx');
        })->name('alumni.export');
    });
    Route::prefix('surat')->name('letters.')->middleware(['module:letters', 'can:letters.manage'])->group(function (): void {
        Route::get('/', LetterCenter::class)->name('index');
        Route::get('/kop/{letterhead}/{which}', function (Letterhead $letterhead, string $which) {
            $path = $which === 'footer' ? $letterhead->footer_path : ($which === 'header' ? $letterhead->header_path : null);
            abort_unless($path && Storage::disk('local')->exists($path), 404);

            return Storage::disk('local')->response($path);
        })->name('kop');
        Route::get('/{letter}/pdf', function (Letter $letter, PdfRenderer $pdf, SchoolIdentity $identity) {
            $letterhead = $letter->letterhead;
            $html = view('letters.pdf', [
                'letter' => $letter,
                'identity' => $identity,
                'profile' => SchoolProfile::query()->first(),
                'header' => $letterhead?->dataUri('header'),
                'footer' => $letterhead?->dataUri('footer'),
                'city' => LetterSettings::all()['city'],
            ])->render();

            return response($pdf->render($html), 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="surat-'.str_replace(['/', '\\'], '-', $letter->letter_no).'.pdf"',
            ]);
        })->name('pdf');
    });
    Route::get('/inventaris', InventoryBoard::class)->middleware(['module:inventory', 'can:inventory.manage'])->name('inventory.index');
    Route::get('/piket', DutyBoard::class)->middleware(['module:duty_roster', 'can:duty.manage'])->name('duty.index');
    Route::middleware(['module:export_dapodik', 'can:export.run'])->group(function (): void {
        Route::get('/ekspor', GovernmentExport::class)->name('export.index');
        Route::get('/ekspor/unduh', function (Request $request) {
            $status = (string) $request->query('status', 'active');
            $rows = AffairsStudent::query()->with(['classroom:id,name', 'guardians'])
                ->when($status !== 'all', fn ($query) => $query->where('status', $status === 'graduated' ? 'graduated' : 'active'))
                ->when($request->filled('kelas'), fn ($query) => $query->where('classroom_id', $request->integer('kelas')))
                ->orderBy('name')->get()->map(function ($student) {
                    $guardian = $student->guardians->first();

                    return [$student->nisn, $student->nis, $student->name, $student->gender, $student->birth_place, $student->birth_date?->toDateString(), $student->address, $student->classroom?->name, $student->entry_date?->toDateString(), $guardian?->name, $guardian?->phone_wa];
                })->all();
            $kind = $request->query('untuk') === 'emis' ? 'emis' : 'dapodik';

            return Excel::download(new ArrayExport(['NISN', 'NIS', 'Nama', 'JK', 'Tempat lahir', 'Tanggal lahir', 'Alamat', 'Kelas', 'Tanggal masuk', 'Nama wali', 'HP wali'], $rows), 'ekspor-'.$kind.'.xlsx');
        })->name('export.download');
    });

    Route::prefix('ppdb')->name('ppdb.')->middleware(['module:ppdb', 'can:ppdb.view'])->group(function (): void {
        Route::get('/periode', AdmissionPeriods::class)->name('periods.index');
        Route::get('/pendaftar', ApplicantList::class)->name('applicants.index');
        Route::get('/pendaftar/{applicant}', ApplicantDetail::class)->name('applicants.show');
        Route::get('/seleksi', AdmissionSelection::class)->name('selection.index');
        Route::get('/dokumen/{document}', [ApplicantFileController::class, 'staff'])->name('document');
    });

    Route::get('/dompet', WalletAdmin::class)->middleware(['module:wallet', 'can:wallet.view'])->name('wallets.index');

    Route::middleware('module:card')->group(function (): void {
        Route::get('/kartu', CardAdmin::class)->middleware('can:cards.view')->name('cards.index');
        Route::middleware('can:cards.manage')->group(function (): void {
            Route::get('/kartu/log', ScanLog::class)->name('cards.scans');
            Route::get('/kartu/log/ekspor', function (Request $request) {
                $rows = ScanLog::query(['result' => $request->query('hasil'), 'scope' => $request->query('scope'), 'date' => $request->query('tanggal')])
                    ->limit(10000)->get()
                    ->map(fn (CardScan $scan) => [
                        $scan->scanned_at->setTimezone(config('sanad.display_timezone'))->format('Y-m-d H:i:s'),
                        $scan->card?->student?->nis,
                        $scan->card?->student?->name,
                        Device::SCOPES[$scan->scope],
                        $scan->device?->name ?? 'Kasir',
                        ScanLog::RESULTS[$scan->result],
                        $scan->reason,
                    ])->all();

                return Excel::download(new ArrayExport(['Waktu', 'NIS', 'Santri', 'Akses', 'Perangkat', 'Hasil', 'Alasan'], $rows), 'log-pindai.xlsx');
            })->name('cards.scans.export');
            Route::get('/kartu/cetak', function (Request $request, BuildCardsPdf $pdf) {
                $cards = StudentCard::query()->where('status', 'active')->whereIn('uuid', array_filter(explode(',', (string) $request->query('kartu')), fn (string $uuid) => Str::isUuid($uuid)))->limit(200)->get();
                abort_if($cards->isEmpty(), 404);

                return response($pdf->handle($cards, (string) $request->query('format')), 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'attachment; filename="kartu-santri.pdf"']);
            })->name('cards.print');
        });
        Route::get('/kartu/perangkat', DeviceAdmin::class)->middleware('can:devices.manage')->name('cards.devices');
    });

    Route::middleware('module:pos')->group(function (): void {
        Route::get('/kasir', Cashier::class)->middleware('can:pos.operate')->name('pos.index');
        Route::get('/kasir/struk/{order}', function (PosOrder $order, Request $request, SchoolIdentity $identity) {
            abort_unless(Merchant::query()->accessibleBy($request->user())->whereKey($order->merchant_id)->exists(), 403);

            return view('pos.receipt', [
                'order' => $order->load(['merchant', 'items', 'cashier:id,name', 'student:id,name', 'walletTransaction']),
                'identity' => $identity,
                'width' => (int) $request->query('lebar') === 80 ? 80 : 58,
            ]);
        })->name('pos.receipt');
        Route::get('/pencairan', PayoutQueue::class)->middleware('can:payouts.approve')->name('payouts.index');

        Route::prefix('merchant')->name('merchants.')->middleware('can:merchants.access')->group(function (): void {
            Route::get('/', MerchantAdmin::class)->name('index');
            Route::get('/laporan', SalesReport::class)->name('report.all');
            Route::get('/laporan/ekspor', function (Request $request) {
                $user = $request->user();
                $accessible = Merchant::query()->accessibleBy($user);

                if ($request->filled('merchant')) {
                    abort_unless(Str::isUuid((string) $request->query('merchant')), 403);
                    $accessible->where('uuid', $request->query('merchant'));
                }

                $ids = $accessible->pluck('id')->all();
                abort_if($ids === [], 403);

                $today = now(config('sanad.display_timezone'))->toDateString();
                $from = SalesReport::validDate($request->query('dari')) ?? $today;
                $to = SalesReport::validDate($request->query('sampai')) ?? $today;
                $summary = SalesStats::summary($ids, $from, $to);

                $rows = [
                    ['Omzet', $summary['revenue'], ''],
                    ['Jumlah transaksi', $summary['orders'], ''],
                    ['Komisi pesantren', $summary['commission'], ''],
                    ['Bagian merchant', $summary['net'], ''],
                    ['Margin', $summary['margin'], $summary['margin_complete'] ? '' : 'Belum lengkap'],
                    ['', '', ''],
                    ...SalesStats::byProduct($ids, $from, $to, 1000)->map(fn ($row) => [$row->name, (int) $row->amount, (int) $row->qty.' terjual'])->all(),
                ];

                return Excel::download(new ArrayExport(["Laporan {$from} sampai {$to}", 'Nominal', 'Catatan'], $rows), 'laporan-mart-warung.xlsx');
            })->name('report.export');
            Route::get('/impor/template', fn () => Excel::download(new ArrayExport(CatalogImport::HEADINGS, []), 'template-katalog.xlsx'))->name('import.template');
            Route::get('/produk/{product}/foto', function (MerchantProduct $product, Request $request) {
                abort_unless($product->photo_path && Merchant::query()->accessibleBy($request->user())->whereKey($product->merchant_id)->exists() && Storage::disk('local')->exists($product->photo_path), 404);

                return Storage::disk('local')->response($product->photo_path);
            })->name('product-photo');
            Route::get('/{merchant}/katalog', ProductCatalog::class)->name('catalog');
            Route::get('/{merchant}/katalog/impor', CatalogImport::class)->name('import');
            Route::get('/{merchant}/daftar-harga', function (Merchant $merchant, Request $request, PdfRenderer $pdf) {
                abort_unless(Merchant::query()->accessibleBy($request->user())->whereKey($merchant->id)->exists(), 403);

                return response($pdf->render(view('pos.pdf.pricelist', ['merchant' => $merchant, 'products' => $merchant->products()->where('is_active', true)->orderBy('name')->get()])->render()), 200, [
                    'Content-Type' => 'application/pdf',
                    'Content-Disposition' => 'attachment; filename="daftar-harga.pdf"',
                ]);
            })->name('pricelist');
            Route::get('/{merchant}/stok', StockScreen::class)->name('stock');
            Route::get('/{merchant}/pembelian', Purchasing::class)->name('purchases');
            Route::get('/{merchant}/promo', Promotions::class)->name('promotions');
            Route::get('/{merchant}/transaksi', OrderHistory::class)->name('orders');
            Route::get('/{merchant}/laporan', SalesReport::class)->name('report');
        });
    });

    Route::get('/santri', StudentIndex::class)->middleware('can:viewAny,'.Student::class)->name('students.index');
    Route::get('/santri/ekspor', fn (Request $request) => Excel::download(new StudentsExport($request->user()), 'santri.xlsx'))
        ->middleware('can:create,'.Student::class)->name('students.export');
    Route::get('/santri/tambah', StudentForm::class)->middleware('can:create,'.Student::class)->name('students.create');
    Route::middleware('can:imports.manage')->group(function (): void {
        Route::get('/santri/impor', StudentImport::class)->name('students.import');
        Route::get('/santri/impor/template', fn () => Excel::download(new ImportTemplateExport, 'template-impor-santri.xlsx'))->name('students.import.template');
        Route::get('/santri/impor/{import}/baris-gagal', fn (Import $import) => Excel::download(new ImportErrorsExport($import), 'baris-gagal-impor.xlsx'))->name('students.import.errors');
    });
    Route::middleware('can:masters.manage')->group(function (): void {
        Route::get('/santri/field-tambahan', CustomFields::class)->name('students.custom-fields');
        Route::get('/santri/kenaikan-kelas', ClassPromotion::class)->name('students.promotion');
    });
    Route::get('/pengumuman', AnnouncementIndex::class)->middleware('can:announcements.manage')->name('announcements.index');
    Route::get('/santri/{student}/foto', function (Student $student) {
        abort_unless(Gate::allows('view', $student) && $student->photo_path, 404);

        return Storage::disk('local')->response($student->photo_path);
    })->name('students.photo');
    Route::get('/santri/{student}/ubah', StudentForm::class)->middleware('can:update,student')->name('students.edit');
    Route::get('/santri/{student}', StudentShow::class)->middleware('can:view,student')->name('students.show');
    Route::get('/wali', GuardianIndex::class)->middleware('can:viewAny,'.Guardian::class)->name('guardians.index');
    Route::get('/kelas-asrama', MasterData::class)->middleware('can:masters.manage')->name('classrooms.index');

    Route::prefix('pengaturan')->name('settings.')->group(function (): void {
        Route::get('/notifikasi', Templates::class)->middleware('can:settings.manage')->name('notifications');
        Route::get('/kesehatan-sistem', SystemHealth::class)->middleware('can:settings.manage')->name('system');
        Route::get('/pengguna', UserIndex::class)->middleware('can:users.manage')->name('users');
        Route::middleware('can:settings.manage')->group(function (): void {
            Route::get('/profil', ProfileSettings::class)->name('profile');
            Route::get('/modul', ModuleSettings::class)->name('modules');
            Route::get('/integrasi', IntegrationSettings::class)->name('integrations');
        });
        Route::get('/audit-log', AuditLogs::class)->middleware('can:audit.view')->name('audit');
    });
});

Route::middleware(['auth', 'user.type:staff', 'role:ustadz'])->prefix('ustadz')->name('ustadz.')->group(function (): void {
    Route::get('/', TeacherHome::class)->name('home');
    Route::get('/absensi', AttendanceToday::class)->middleware('module:attendance')->name('attendance');
    Route::get('/absensi/tinjauan', AttendanceReview::class)->middleware('module:attendance')->name('attendance.review');
    Route::get('/absensi/{session}', AttendanceSheet::class)->middleware('module:attendance')->name('attendance.session');
    Route::get('/nilai', GradeBook::class)->middleware(['module:grading', 'can:grades.input'])->name('grades');
    Route::get('/tahfidz', SetoranForm::class)->middleware('module:tahfidz')->name('tahfidz');
    Route::get('/mutabaah', MutabaahVerify::class)->middleware('module:tahfidz')->name('mutabaah');

    Route::prefix('ujian')->name('exams.')->middleware(['module:exam', 'can:exams.own'])->group(function (): void {
        Route::get('/', ExamList::class)->name('index');
        Route::get('/bank-soal', QuestionBanks::class)->name('banks');
        Route::get('/{exam}/susun', ExamWizard::class)->name('edit');
        Route::get('/{exam}/monitor', ExamMonitor::class)->name('monitor');
        Route::get('/{exam}/hasil', ExamResults::class)->name('results');
    });
    Route::get('/ujian-pintas', fn () => redirect()->route('ustadz.exams.index'))->middleware('module:exam')->name('exams');
});

Route::middleware(['auth', 'user.type:staff', 'role:musyrif'])->prefix('musyrif')->name('musyrif.')->group(function (): void {
    Route::get('/pelanggaran', DisciplineBoard::class)->middleware('module:discipline')->name('discipline');
    Route::get('/', TeacherHome::class)->name('home');
    Route::get('/izin', LeaveDesk::class)->middleware('module:leave')->name('leaves');
    Route::get('/tahfidz', SetoranForm::class)->middleware('module:tahfidz')->name('tahfidz');
    Route::get('/mutabaah', MutabaahVerify::class)->middleware('module:tahfidz')->name('mutabaah');
    Route::get('/absensi', AttendanceToday::class)->middleware('module:attendance')->name('attendance');
    Route::get('/absensi/tinjauan', AttendanceReview::class)->middleware('module:attendance')->name('attendance.review');
    Route::get('/absensi/{session}', AttendanceSheet::class)->middleware('module:attendance')->name('attendance.session');
});

Route::middleware(['auth', 'user.type:guardian', 'module:portal_wali'])->prefix('wali')->name('wali.')->group(function (): void {
    Route::view('/', 'core.wali.home')->name('home');
    Route::get('/lainnya', GuardianMore::class)->name('more');
    Route::get('/persetujuan-layar', GuardianDisplayConsent::class)->middleware('module:masjid_display')->name('display-consent');
    Route::get('/persetujuan-wajah', GuardianFaceConsent::class)->middleware('module:face_attendance')->name('face-consent');
    Route::get('/perkembangan', StudentAcademics::class)->name('progress');
    Route::get('/tahfidz', StudentTahfidz::class)->middleware('module:tahfidz')->name('tahfidz');
    Route::get('/izin', GuardianLeaves::class)->middleware('module:leave')->name('leaves');

    Route::middleware('module:finance')->group(function (): void {
        Route::get('/tagihan', GuardianInvoices::class)->name('invoices');
        Route::get('/tagihan/{invoice}', GuardianInvoiceShow::class)->middleware('can:view,invoice')->name('invoices.show');
        Route::get('/bayar', GuardianCheckout::class)->name('checkout');
        Route::get('/pembayaran/{payment}', GuardianPayment::class)->middleware('can:view,payment')->name('payments.show');
    });

    Route::middleware('module:wallet')->group(function (): void {
        Route::get('/dompet', GuardianWallet::class)->name('wallet');
        Route::get('/dompet/top-up', GuardianTopup::class)->name('wallet.topup');
        Route::get('/dompet/aturan', GuardianSpendingRules::class)->middleware('module:pos')->name('wallet.rules');
    });
});

Route::middleware(['auth', 'user.type:student', 'module:portal_santri'])->prefix('santri')->name('santri.')->group(function (): void {
    Route::view('/', 'core.santri.home')->name('home');
    Route::get('/kartu', StudentCardPage::class)->middleware('module:card')->name('card');
    Route::get('/jadwal', StudentAcademics::class)->middleware('module:academic')->name('schedule');
    Route::get('/ujian', StudentExams::class)->middleware('module:exam')->name('exams');
    Route::get('/ujian/{exam}/persiapan', ExamPrepare::class)->middleware('module:exam')->name('exams.prepare');
    Route::get('/tahfidz', StudentTahfidz::class)->middleware('module:tahfidz')->name('tahfidz');
    Route::get('/izin', GuardianLeaves::class)->middleware('module:leave')->name('leaves');
});

Route::get('/kwitansi/{payment}', function (Payment $payment, BuildReceiptPdf $pdf) {
    try {
        $content = $pdf->handle($payment);
    } catch (FinanceException $exception) {
        abort(404, $exception->getMessage());
    }

    return response($content, 200, [
        'Content-Type' => 'application/pdf',
        'Content-Disposition' => 'attachment; filename="kwitansi-'.$payment->payment_no.'.pdf"',
    ]);
})->middleware(['auth', 'module:finance', 'can:view,payment'])->name('payments.receipt');

Route::get('/rapor/{card}', function (ReportCard $card, Request $request, BuildReportCardPdf $pdf) {
    $user = $request->user();
    $visible = Student::query()->visibleTo($user)->whereKey($card->student_id)->exists();
    $staff = $user->canAny(['reports.publish', 'grades.manage']);

    abort_unless($staff || ($visible && $card->status === 'published'), 403);

    return response($pdf->handle($card), 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'attachment; filename="rapor-'.$card->student->nis.'.pdf"']);
})->middleware(['auth', 'module:grading'])->name('report-cards.pdf');

Route::middleware(['auth', 'user.type:staff', 'module:attendance'])->group(function (): void {
    Route::get('/absensi-bukti/{record}', [AttendanceCameraController::class, 'evidence'])->name('attendance.evidence');
    Route::middleware('module:face_attendance')->group(function (): void {
        Route::get('/absensi-wajah/{session}', [AttendanceCameraController::class, 'kiosk'])->name('attendance.face');
        Route::post('/absensi-wajah/{session}/tantangan', [AttendanceCameraController::class, 'faceChallenge'])->middleware('throttle:60,1')->name('attendance.face.challenge');
        Route::post('/absensi-wajah/{session}/pindai', [AttendanceCameraController::class, 'faceScan'])->middleware('throttle:60,1')->name('attendance.face.scan');
    });
});

Route::middleware(['auth', 'module:exam'])->group(function (): void {
    Route::middleware('user.type:student')->group(function (): void {
        Route::get('/ujian/{attempt}', [AttemptController::class, 'show'])->name('exam.take');
        Route::get('/ujian/{attempt}/selesai', [AttemptController::class, 'done'])->name('exam.done');
        Route::post('/ujian/{attempt}/jawaban', [AttemptController::class, 'answers'])->middleware('throttle:240,1')->name('exam.answers');
        Route::post('/ujian/{attempt}/kumpulkan', [AttemptController::class, 'submit'])->name('exam.submit');
        Route::post('/ujian/{attempt}/peristiwa', [AttemptController::class, 'events'])->middleware('throttle:240,1')->name('exam.events');
    });
    Route::get('/ujian-media/{question}', [AttemptController::class, 'media'])->name('exam.media');
    Route::get('/ujian-template-impor', fn () => Excel::download(new ArrayExport(QuestionBanks::HEADINGS, []), 'template-soal.xlsx'))->middleware('can:exams.import')->name('exam.import-template');
    Route::get('/ujian-hasil/{exam}/ekspor', function (Exam $exam, Request $request) {
        abort_unless($exam->canBeManagedBy($request->user()), 403);
        $rows = $exam->attempts()->with('student:id,name,nis')->whereNot('status', 'in_progress')->orderByDesc('score')->get()
            ->map(fn ($attempt) => [$attempt->student?->nis, $attempt->student?->name, $attempt->score, ExamAttempt::STATUSES[$attempt->status], $attempt->blur_count])->all();

        return Excel::download(new ArrayExport(['NIS', 'Santri', 'Nilai', 'Status', 'Pindah layar'], $rows), 'hasil-ujian.xlsx');
    })->name('exam.results-export');
});

Route::get('/verifikasi-kwitansi/{payment}', fn (Payment $payment) => view('finance.receipts.verify', ['payment' => $payment]))
    ->middleware('throttle:30,1')
    ->name('receipts.verify');

// Hanya untuk uji browser: alamat order diperlukan untuk menyusun notifikasi bertanda tangan.
if (config('sanad.midtrans.fake') && ! app()->isProduction()) {
    Route::get('/_uji/order/{payment}', fn (Payment $payment) => ['orderId' => $payment->gateway_order_id, 'gross' => $payment->gross_amount.'.00']);
}

Route::middleware(EnsurePreviewAllowed::class)->prefix('_pratinjau')->name('preview.')->group(function (): void {
    Route::view('/', 'preview.gallery')->name('gallery');
    Route::view('/staf', 'preview.staff')->name('staff');
    Route::get('/portal/{role}', fn (string $role) => view('preview.portal', ['role' => $role]))
        ->whereIn('role', ['wali', 'santri', 'ustadz', 'musyrif'])
        ->name('portal');
});
