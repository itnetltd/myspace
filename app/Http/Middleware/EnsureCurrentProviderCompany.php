<?php

namespace App\Http\Middleware;

use App\Support\CurrentProviderCompany;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCurrentProviderCompany
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(app(CurrentProviderCompany::class)->company(), 403, 'No active provider company is available.');

        return $next($request);
    }
}
