<?php

namespace Jointdots\FiskalizimiKs\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class FiscalApiTokenMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = config('fiskalizimi.api.token');

        if (empty($expected)) {
            abort(500, 'FISCAL_API_TOKEN is not configured.');
        }

        $provided = $request->bearerToken();

        if ($provided === null || !hash_equals($expected, $provided)) {
            abort(401, 'Unauthorized.');
        }

        return $next($request);
    }
}
