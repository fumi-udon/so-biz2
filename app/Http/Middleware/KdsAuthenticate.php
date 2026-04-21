<?php

namespace App\Http\Middleware;

use App\Models\Shop;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class KdsAuthenticate
{
    /**
     * KDS: セッションに有効な店舗スコープがあることのみを要求する（端末 PIN で事前ログイン）。
     */
    public function handle(Request $request, Closure $next): Response
    {
        $shopId = $request->session()->get('kds.active_shop_id');

        if ($shopId === null || (int) $shopId < 1) {
            return redirect()->guest(route('kds.login'));
        }

        $ok = Shop::query()
            ->whereKey((int) $shopId)
            ->where('is_active', true)
            ->exists();

        if (! $ok) {
            $request->session()->forget('kds.active_shop_id');

            return redirect()->guest(route('kds.login'));
        }

        return $next($request);
    }
}
