<?php

namespace App\Http\Middleware;

use App\Support\Http\ServerTimingCollector;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Livewire の update 等の Web 応答に Server-Timing を付与する（Chrome DevTools → Network → Timing）。
 *
 * DB 集計は {@see AppServiceProvider} の単一 DB::listen から
 * {@see \App\Support\Http\ServerTimingCollector} に流す（ミドルウェア毎に listen 登録しない＝リスナー蓄積を防ぐ）。
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
