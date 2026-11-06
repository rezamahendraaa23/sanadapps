<?php

use App\Http\Middleware\EnsurePreviewAllowed;
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
use App\Modules\Core\Models\Guardian;
use App\Modules\Core\Models\Import;
use App\Modules\Core\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;

Route::view('/', 'home')->name('home');

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

Route::middleware(['auth', 'user.type:guardian', 'module:portal_wali'])->prefix('wali')->name('wali.')->group(function (): void {
    Route::view('/', 'core.wali.home')->name('home');
});

Route::middleware(['auth', 'user.type:student', 'module:portal_santri'])->prefix('santri')->name('santri.')->group(function (): void {
    Route::view('/', 'core.santri.home')->name('home');
});

Route::middleware(EnsurePreviewAllowed::class)->prefix('_pratinjau')->name('preview.')->group(function (): void {
    Route::view('/', 'preview.gallery')->name('gallery');
    Route::view('/staf', 'preview.staff')->name('staff');
    Route::get('/portal/{role}', fn (string $role) => view('preview.portal', ['role' => $role]))
        ->whereIn('role', ['wali', 'santri', 'ustadz', 'musyrif'])
        ->name('portal');
});
