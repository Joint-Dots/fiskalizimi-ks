<?php

namespace Jointdots\FiskalizimiKs\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class LogFiscalRequestMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!config('fiskalizimi.api.log_requests', false)) {
            return $next($request);
        }

        $start = microtime(true);

        Log::debug('fiscal.request', [
            'method'          => $request->method(),
            'path'            => $request->path(),
            'ip'              => $request->ip(),
            'idempotency_key' => $request->input('idempotency_key'),
            'operator_id'     => $request->input('operator_id'),
            'item_count'      => count((array) $request->input('items', [])),
            'payment_count'   => count((array) $request->input('payments', [])),
            'total'           => $request->input('total'),
        ]);

        /** @var Response $response */
        $response = $next($request);

        Log::debug('fiscal.response', [
            'method'  => $request->method(),
            'path'    => $request->path(),
            'status'  => $response->getStatusCode(),
            'ms'      => round((microtime(true) - $start) * 1000),
        ]);

        return $response;
    }
}
