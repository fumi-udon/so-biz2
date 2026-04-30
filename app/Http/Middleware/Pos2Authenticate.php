<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class Pos2Authenticate
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->session()->get('pos2_authenticated') !== true) {
            return redirect()->route('pos2.login');
        }

        return $next($request);
    }
}
