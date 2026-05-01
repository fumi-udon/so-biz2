<?php

namespace App\Http\Controllers\Pos2;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class Pos2AuthController extends Controller
{
    public function showLoginForm(): View|RedirectResponse
    {
        if (session('pos2_authenticated') === true) {
            return redirect()->route('pos2.index');
        }

        return view('pos2.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'pin' => ['required', 'string', 'regex:/^\d{4}$/'],
        ]);

        $expectedPin = (string) env('POS2_PASS', '3333');
        $enteredPin = (string) $validated['pin'];

        if (! hash_equals($expectedPin, $enteredPin)) {
            return back()
                ->withInput()
                ->withErrors(['pin' => 'PINが違います。']);
        }

        $request->session()->regenerate();
        $request->session()->put('pos2_authenticated', true);

        $settlementActorId = (int) config('app.pos2_settlement_actor_user_id', 0);
        if ($settlementActorId >= 1) {
            $request->session()->put('pos2.settlement_actor_user_id', $settlementActorId);
        }

        return redirect()->intended(route('pos2.index'));
    }

    public function logout(Request $request): RedirectResponse
    {
        $request->session()->forget('pos2_authenticated');
        $request->session()->forget('pos2.settlement_actor_user_id');
        $request->session()->regenerateToken();

        return redirect()->route('pos2.login');
    }
}
