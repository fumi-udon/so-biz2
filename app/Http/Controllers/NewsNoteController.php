<?php

namespace App\Http\Controllers;

use App\Models\NewsNote;
use App\Models\Staff;
use App\Support\BusinessDate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;

class NewsNoteController extends Controller
{
    private const MIN_LEVEL = 4;

    private const SESSION_KEY = 'news_staff_id';

    private const RATE_LIMIT_MAX = 5;

    private const RATE_LIMIT_DECAY = 300;

    /**
     * PIN 認証（manager または job_level >= 4 を要求）。
     */
    public function auth(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'staff_id' => ['required', 'integer', 'exists:staff,id'],
            'pin_code' => ['required', 'string', 'digits:4'],
        ], [
            'staff_id.required' => 'Choisis un responsable.',
            'pin_code.required' => 'PIN 4 chiffres requis.',
            'pin_code.digits' => 'PIN: 4 chiffres.',
        ]);

        $staffId = (int) $validated['staff_id'];
        $rateLimitKey = 'news-pin:'.$staffId.':'.(request()->ip() ?? 'unknown');

        if (RateLimiter::tooManyAttempts($rateLimitKey, self::RATE_LIMIT_MAX)) {
            return redirect()->route('home')->with('error', 'Trop de tentatives PIN. Patientez quelques minutes.');
        }

        $staff = Staff::query()
            ->with('jobLevel')
            ->where('is_active', true)
            ->find($staffId);

        if (! $staff) {
            RateLimiter::hit($rateLimitKey, self::RATE_LIMIT_DECAY);

            return redirect()->route('home')->with('error', 'Personnel introuvable ou inactif.');
        }

        if ($staff->pin_code === null || $staff->pin_code === '') {
            return redirect()->route('home')->with('error', 'PIN non configure pour ce staff.');
        }

        if (! hash_equals((string) $staff->pin_code, (string) $validated['pin_code'])) {
            RateLimiter::hit($rateLimitKey, self::RATE_LIMIT_DECAY);

            return redirect()->route('home')->with('error', 'PIN incorrect.');
        }

        $level = (int) ($staff->jobLevel?->level ?? 0);
        $isManager = (bool) $staff->is_manager;

        if (! $isManager && $level < self::MIN_LEVEL) {
            RateLimiter::hit($rateLimitKey, self::RATE_LIMIT_DECAY);

            return redirect()->route('home')->with('error', 'Acces refuse. Niveau '.(self::MIN_LEVEL).'+ ou manager requis.');
        }

        RateLimiter::clear($rateLimitKey);
        $request->session()->put(self::SESSION_KEY, $staff->id);

        return redirect()->route('news.manage');
    }

    /**
     * 管理ページ：一覧・追加・編集・削除。
     */
    public function manage(Request $request): View|RedirectResponse
    {
        $staffId = $request->session()->get(self::SESSION_KEY);
        if (! $staffId) {
            return redirect()->route('home')->with('error', 'Authentification requise pour acceder aux notes.');
        }

        $editorStaff = Staff::query()->find($staffId);
        if (! $editorStaff) {
            $request->session()->forget(self::SESSION_KEY);

            return redirect()->route('home')->with('error', 'Session expiree. Reconnectez-vous.');
        }

        $notes = NewsNote::query()
            ->with('staff:id,name')
            ->orderByDesc('posted_date')
            ->orderByDesc('created_at')
            ->limit(200)
            ->get();

        $editNote = null;
        if ($request->has('edit')) {
            $editNote = $notes->firstWhere('id', (int) $request->integer('edit'));
        }

        return view('news.manage', [
            'notes' => $notes,
            'editorStaff' => $editorStaff,
            'editNote' => $editNote,
            'today' => BusinessDate::current()->toDateString(),
        ]);
    }

    /**
     * 新規保存。
     */
    public function store(Request $request): RedirectResponse
    {
        $staffId = $request->session()->get(self::SESSION_KEY);
        if (! $staffId) {
            return redirect()->route('home')->with('error', 'Session expiree.');
        }

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:120'],
            'body' => ['required', 'string', 'max:2000'],
            'posted_date' => ['required', 'date'],
        ]);

        NewsNote::query()->create([
            'staff_id' => $staffId,
            'title' => $validated['title'],
            'body' => $validated['body'],
            'posted_date' => $validated['posted_date'],
        ]);

        return redirect()->route('news.manage')->with('status', 'Note ajoutee.');
    }

    /**
     * 更新。
     */
    public function update(Request $request, int $id): RedirectResponse
    {
        $staffId = $request->session()->get(self::SESSION_KEY);
        if (! $staffId) {
            return redirect()->route('home')->with('error', 'Session expiree.');
        }

        $note = NewsNote::query()->findOrFail($id);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:120'],
            'body' => ['required', 'string', 'max:2000'],
            'posted_date' => ['required', 'date'],
        ]);

        $note->update($validated);

        return redirect()->route('news.manage')->with('status', 'Note mise a jour.');
    }

    /**
     * 削除。
     */
    public function destroy(Request $request, int $id): RedirectResponse
    {
        $staffId = $request->session()->get(self::SESSION_KEY);
        if (! $staffId) {
            return redirect()->route('home')->with('error', 'Session expiree.');
        }

        NewsNote::query()->findOrFail($id)->delete();

        return redirect()->route('news.manage')->with('status', 'Note supprimee.');
    }

    /**
     * セッション解除してトップへ。
     */
    public function logout(Request $request): RedirectResponse
    {
        $request->session()->forget(self::SESSION_KEY);

        return redirect()->route('home');
    }
}
