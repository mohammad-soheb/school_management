<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Only the product owner (Super Admin) reaches the /platform backoffice. */
class EnsurePlatformAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->user()?->isPlatformAdmin(), 403, 'Platform administrators only.');

        return $next($request);
    }
}
