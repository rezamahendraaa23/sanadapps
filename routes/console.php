<?php

use App\Modules\Finance\Actions\ApplyLateFees;
use App\Modules\Finance\Actions\GenerateMonthlyInvoices;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Jam dihitung dalam zona waktu pesantren (PRD Sistem 8.3 dan 8.14).
Schedule::call(fn () => app(ApplyLateFees::class)->handle())->name('MarkOverdue')->dailyAt('00:30')->timezone(config('sanad.display_timezone'))->withoutOverlapping();
Schedule::call(fn () => app(GenerateMonthlyInvoices::class)->handle())->name('GenerateInvoices')->dailyAt('01:00')->timezone(config('sanad.display_timezone'))->withoutOverlapping();
