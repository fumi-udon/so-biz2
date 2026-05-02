<?php

namespace App\Http\Controllers;

use App\Models\Shop;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class KdsAuthController extends Controller
{
    public function showLoginForm(Request $request): View|RedirectResponse
    {
        $sid = $request->session()->get('kds.active_shop_id');
        if ($sid !== null && (int) $sid > 0) {
            $ok = Shop::query()
                ->whereKey((int) $sid)
                ->where('is_active', true)
                ->exists();
            if ($ok) {
                return redirect()->route('kds2.index');
            }
        }

        return view('kds.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'pin' => ['required', 'string', 'regex:/^\d{4}$/'],
        ]);

        $pin = (string) $validated['pin'];
        /** @var array<string, int|string> $map */
        $map = config('kds.pin_to_shop', []);
        $shopId = isset($map[$pin]) ? (int) $map[$pin] : 0;

        if ($shopId < 1) {
            return back()
                ->withInput()
                ->withErrors(['pin' => __('kds.invalid_pin')]);
        }

        $exists = Shop::query()
            ->whereKey($shopId)
            ->where('is_active', true)
            ->exists();

        if (! $exists) {
            return back()
                ->withInput()
                ->withErrors(['pin' => __('kds.invalid_pin')]);
        }

        $request->session()->put('kds.active_shop_id', $shopId);

        return redirect()->intended(route('kds2.index'));
    }
}
