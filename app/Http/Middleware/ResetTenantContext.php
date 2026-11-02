<?php

namespace App\Http\Middleware;

use App\Support\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Global middleware: every request starts with no school and leaves none behind, so nothing can carry over between
 * requests served by the same process (Octane, queue workers, tests).
 */
class ResetTenantContext
{
    public function __construct(private readonly TenantContext $context) {}

    public function handle(Request $request, Closure $next): Response
    {
        $this->context->clear();

        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        $this->context->clear();
    }
}
