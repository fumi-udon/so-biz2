<?php

use App\Http\Middleware\AppendServerTiming;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\KdsAuthenticate;
use App\Http\Middleware\Pos2Authenticate;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // OVH 等のリバースプロキシ配下で HTTPS / セッション / リダイレクトが正しく効くようにする
        $middleware->trustProxies(at: '*');

        $middleware->web(append: [
            AppendServerTiming::class,
            HandleInertiaRequests::class,
        ]);

        $middleware->alias([
            'kds.auth' => KdsAuthenticate::class,
            'pos2.auth' => Pos2Authenticate::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
