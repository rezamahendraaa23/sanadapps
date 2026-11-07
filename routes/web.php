<?php

use App\Http\Middleware\EnsurePreviewAllowed;
use App\Modules\Academic\Actions\BuildReportCardPdf;
use App\Modules\Academic\Livewire\AcademicMasters;
use App\Modules\Academic\Livewire\AttendanceRecap;
use App\Modules\Academic\Livewire\AttendanceSheet;
use App\Modules\Academic\Livewire\AttendanceToday;
use App\Modules\Academic\Livewire\GradeBook;
use App\Modules\Academic\Livewire\ReportCards;
use App\Modules\Academic\Livewire\ScheduleBuilder;
use App\Modules\Academic\Livewire\StudentAcademics;
use App\Modules\Academic\Livewire\TeacherHome;
use App\Modules\Academic\Models\ReportCard;
use App\Modules\Academic\Support\AttendanceStats;
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
use App\Modules\Core\Models\Student;
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
use App\Modules\Notification\Http\FonnteWebhookController;
use App\Modules\Notification\Livewire\GuardianMore;
use App\Modules\Notification\Livewire\Templates;
use App\Modules\Payment\Http\MidtransWebhookController;
use App\Modules\Payment\Livewire\Reconciliation;
use App\Modules\Pos\Livewire\Cashier;
use App\Modules\Pos\Livewire\CatalogImport;
use App\Modules\Pos\Livewire\MerchantAdmin;
use App\Modules\Pos\Livewire\PayoutQueue;
use App\Modules\Pos\Livewire\ProductCatalog;
use App\Modules\Pos\Livewire\SalesReport;
use App\Modules\Pos\Livewire\StockScreen;
use App\Modules\Pos\Models\Merchant;
use App\Modules\Pos\Models\MerchantProduct;
use App\Modules\Pos\Support\SalesStats;
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
    });

    Route::middleware('module:grading')->group(function (): void {
        Route::get('/nilai', GradeBook::class)->middleware('can:grades.manage')->name('grades.index');
        Route::get('/rapor', ReportCards::class)->middleware('can:reports.publish')->name('report-cards.index');
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

                return response($pdf->handle($cards), 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'attachment; filename="kartu-santri.pdf"']);
            })->name('cards.print');
        });
        Route::get('/kartu/perangkat', DeviceAdmin::class)->middleware('can:devices.manage')->name('cards.devices');
    });

    Route::middleware('module:pos')->group(function (): void {
        Route::get('/kasir', Cashier::class)->middleware('can:pos.operate')->name('pos.index');
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
    Route::get('/absensi/{session}', AttendanceSheet::class)->middleware('module:attendance')->name('attendance.session');
    Route::get('/nilai', GradeBook::class)->middleware(['module:grading', 'can:grades.input'])->name('grades');
});

Route::middleware(['auth', 'user.type:staff', 'role:musyrif'])->prefix('musyrif')->name('musyrif.')->group(function (): void {
    Route::get('/', TeacherHome::class)->name('home');
    Route::get('/absensi', AttendanceToday::class)->middleware('module:attendance')->name('attendance');
    Route::get('/absensi/{session}', AttendanceSheet::class)->middleware('module:attendance')->name('attendance.session');
});

Route::middleware(['auth', 'user.type:guardian', 'module:portal_wali'])->prefix('wali')->name('wali.')->group(function (): void {
    Route::view('/', 'core.wali.home')->name('home');
    Route::get('/lainnya', GuardianMore::class)->name('more');
    Route::get('/perkembangan', StudentAcademics::class)->name('progress');

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
