<?php

namespace App\Http\Middleware;

use App\Support\Http\ServerTimingCollector;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Adds Server-Timing for wall / DB / remainder when app.server_timing is true.
 *
 * @see https://developer.mozilla.org/en-US/docs/Web/HTTP/Headers/Server-Timing
 */
final class AppendServerTiming
{
    public function handle(Request $request, Closure $next): mixed
    {
        if (! config('app.server_timing')) {
            return $next($request);
        }

        /** @var ServerTimingCollector $collector */
        $collector = app(ServerTimingCollector::class);
        $collector->begin();

        /** @var Response $response */
        $response = $next($request);

        if ($response instanceof Response) {
            $collector->attachToResponse($response);
        }

        return $response;
    }
}
