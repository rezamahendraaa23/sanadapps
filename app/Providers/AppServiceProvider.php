<?php

namespace App\Providers;

use App\Modules\Core\Models\SchoolProfile;
use App\Modules\Core\Support\ModuleRegistry;
use App\Support\SchoolIdentity;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(ModuleRegistry::class);
        $this->app->scoped(SchoolIdentity::class);
    }

    public function boot(): void
    {
        SchoolProfile::saved(fn () => app(SchoolIdentity::class)->forget());
    }
}
