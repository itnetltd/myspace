<?php

namespace App\Http\Middleware;

use App\Support\CurrentAccount;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCurrentAccount
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(app(CurrentAccount::class)->account(), 403, 'No active account is available.');

        return $next($request);
    }
}
