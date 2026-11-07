<?php

use App\Modules\Academic\Actions\ManageBiometricConsent;
use App\Modules\Academic\Actions\PurgeAttendanceEvidence;
use App\Modules\Admission\Actions\AdmissionFlow;
use App\Modules\Affairs\Actions\ComputeRiskScores;
use App\Modules\Affairs\Actions\SyncAlumni;
use App\Modules\Core\Support\ModuleRegistry;
use App\Modules\Exam\Actions\RunAttempt;
use App\Modules\Finance\Actions\ApplyLateFees;
use App\Modules\Finance\Actions\GenerateMonthlyInvoices;
use App\Modules\Leave\Actions\ManageLeave;
use App\Modules\Masjid\Actions\ApplyPrayerToSanad;
use App\Modules\Masjid\Actions\ComputePrayerTimes;
use App\Modules\Masjid\Actions\ManageScreens;
use App\Modules\Notification\Actions\SendAnnouncement;
use App\Modules\Notification\Actions\SendPaymentReminders;
use App\Modules\Payment\Actions\SyncPayment;
use App\Modules\Wallet\Actions\ReconcileWallets;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Jam dihitung dalam zona waktu pesantren (PRD Sistem 8.3 dan 8.14).
Schedule::call(fn () => app(ApplyLateFees::class)->handle())->name('MarkOverdue')->dailyAt('00:30')->timezone(config('sanad.display_timezone'))->withoutOverlapping();
Schedule::call(fn () => app(GenerateMonthlyInvoices::class)->handle())->name('GenerateInvoices')->dailyAt('01:00')->timezone(config('sanad.display_timezone'))->withoutOverlapping();
Schedule::call(fn () => app(SyncPayment::class)->pending())->name('SyncPendingPayments')->everyFifteenMinutes()->withoutOverlapping();
Schedule::call(fn () => app(SendPaymentReminders::class)->handle())->name('SendPaymentReminders')->dailyAt('08:00')->timezone(config('sanad.display_timezone'))->withoutOverlapping();
Schedule::call(fn () => app(SendAnnouncement::class)->due())->name('SendScheduledAnnouncements')->everyFiveMinutes()->withoutOverlapping();
Schedule::call(fn () => app(ReconcileWallets::class)->handle())->name('ReconcileWallets')->dailyAt('01:00')->timezone(config('sanad.display_timezone'))->withoutOverlapping();
Schedule::call(fn () => app(ManageLeave::class)->markLate())->name('MarkLateReturns')->everyTenMinutes()->withoutOverlapping();
Schedule::call(fn () => app(RunAttempt::class)->autoSubmitExpired())->name('AutoSubmitExams')->everyMinute()->withoutOverlapping();
Schedule::call(fn () => app(PurgeAttendanceEvidence::class)->handle())->name('PurgeAttendanceEvidence')->dailyAt('02:00')->timezone(config('sanad.display_timezone'));
Schedule::call(fn () => app(ManageBiometricConsent::class)->sweep())->name('SweepFaceData')->dailyAt('02:15')->timezone(config('sanad.display_timezone'));
Schedule::call(fn () => app(AdmissionFlow::class)->announceDue())->name('AnnounceAdmissions')->everyFiveMinutes()->withoutOverlapping();
Schedule::call(fn () => app(AdmissionFlow::class)->closeExpired())->name('CloseAdmissionPeriods')->everyFiveMinutes();
Schedule::call(function () {
    if (app(ModuleRegistry::class)->enabled('analytics')) {
        app(ComputeRiskScores::class)->handle();
    }
})->name('ComputeRiskScores')->dailyAt('03:00')->timezone(config('sanad.display_timezone'));
Schedule::call(fn () => app(SyncAlumni::class)->handle())->name('SyncAlumni')->dailyAt('03:30')->timezone(config('sanad.display_timezone'));
Schedule::call(function () {
    if (app(ModuleRegistry::class)->enabled('masjid_prayer')) {
        app(ComputePrayerTimes::class)->handle();
        app(ApplyPrayerToSanad::class)->shiftAnchoredSchedules();
    }
})->name('ComputePrayerTimes')->dailyAt('00:10')->timezone(config('sanad.display_timezone'));
Schedule::call(function () {
    if (app(ModuleRegistry::class)->enabled('masjid_prayer') && app(ModuleRegistry::class)->enabled('attendance')) {
        app(ApplyPrayerToSanad::class)->openAttendance();
    }
})->name('OpenPrayerAttendance')->everyMinute()->withoutOverlapping();
Schedule::call(fn () => app(ManageScreens::class)->pruneEvents())->name('PruneDisplayEvents')->dailyAt('02:30')->timezone(config('sanad.display_timezone'));
