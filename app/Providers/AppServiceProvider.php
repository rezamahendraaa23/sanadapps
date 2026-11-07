<?php

namespace App\Providers;

use App\Modules\Core\Contracts\OtpSender;
use App\Modules\Core\Models\Guardian;
use App\Modules\Core\Models\SchoolProfile;
use App\Modules\Core\Models\Student;
use App\Modules\Core\Policies\GuardianPolicy;
use App\Modules\Core\Policies\StudentPolicy;
use App\Modules\Core\Support\LogOtpSender;
use App\Modules\Core\Support\ModuleRegistry;
use App\Modules\Finance\Models\Invoice;
use App\Modules\Finance\Models\Payment;
use App\Modules\Finance\Policies\InvoicePolicy;
use App\Modules\Finance\Policies\PaymentPolicy;
use App\Support\Pdf\BrowsershotPdfRenderer;
use App\Support\Pdf\PdfRenderer;
use App\Support\SchoolIdentity;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Laravel\Fortify\Fortify;
use Livewire\Livewire;
use Symfony\Component\Finder\Finder;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(ModuleRegistry::class);
        $this->app->scoped(SchoolIdentity::class);
        $this->app->bind(OtpSender::class, LogOtpSender::class);
        $this->app->bind(PdfRenderer::class, BrowsershotPdfRenderer::class);

        // Masuk memakai layar sendiri; Fortify hanya dipakai untuk logika 2FA.
        Fortify::ignoreRoutes();
    }

    public function boot(): void
    {
        SchoolProfile::saved(fn () => app(SchoolIdentity::class)->forget());
        Paginator::defaultView('components.ui.pagination');

        Gate::policy(Student::class, StudentPolicy::class);
        Gate::policy(Guardian::class, GuardianPolicy::class);
        Gate::policy(Invoice::class, InvoicePolicy::class);
        Gate::policy(Payment::class, PaymentPolicy::class);

        $this->registerModuleComponents();
    }

    /**
     * Livewire hanya mengenali App\Livewire secara otomatis, jadi komponen per modul didaftarkan di sini.
     */
    private function registerModuleComponents(): void
    {
        $root = app_path('Modules');

        foreach (Finder::create()->files()->in($root)->path('Livewire')->name('*.php') as $file) {
            $class = 'App\\Modules\\'.str_replace(['/', '\\'], '\\', substr($file->getRelativePathname(), 0, -4));
            $alias = strtolower(str_replace(['\\Livewire', '\\'], ['', '.'], substr($class, strlen('App\\Modules\\'))));

            Livewire::component($alias, $class);
        }
    }
}
