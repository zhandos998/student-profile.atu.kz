<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureClearanceRequestsEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(config('app.clearance_requests_enabled', false), 404);

        return $next($request);
    }
}
