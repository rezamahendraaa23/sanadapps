<?php

use App\Http\Middleware\EnsurePreviewAllowed;
use App\Modules\Core\Actions\RecordAudit;
use App\Modules\Core\Livewire\Auth\ForgotPassword;
use App\Modules\Core\Livewire\Auth\Login;
use App\Modules\Core\Livewire\Auth\ResetPassword;
use App\Modules\Core\Livewire\Auth\TwoFactorChallenge;
use App\Modules\Core\Livewire\Auth\VerifyOtp;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

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
    Route::view('/', 'core.staff.dashboard')->name('dashboard');
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
