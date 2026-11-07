<?php

use App\Modules\Exam\Actions\RunAttempt;
use App\Modules\Finance\Actions\ApplyLateFees;
use App\Modules\Finance\Actions\GenerateMonthlyInvoices;
use App\Modules\Leave\Actions\ManageLeave;
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
