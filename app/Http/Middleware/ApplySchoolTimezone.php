<?php

namespace App\Http\Middleware;

use App\Support\SchoolIdentity;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApplySchoolTimezone
{
    public function __construct(private SchoolIdentity $identity) {}

    public function handle(Request $request, Closure $next): Response
    {
        config(['sanad.display_timezone' => $this->identity->timezone()]);

        return $next($request);
    }
}
