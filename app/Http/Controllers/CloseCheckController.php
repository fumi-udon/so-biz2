<?php

namespace App\Http\Controllers;

use App\Models\CloseCheckLog;
use App\Models\CloseTask;
use App\Models\Staff;
use App\Services\RoutineInventoryCompletionService;
use App\Support\BusinessDate;
use App\Support\ShiftClockOutGate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;

class CloseCheckController extends Controller
{
    public function index(): View
    {
        $tasks = CloseTask::query()
            ->where('is_active', true)
            ->orderBy('id')
            ->get();

        $staffList = Staff::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $incompleteLines = app(RoutineInventoryCompletionService::class)->globalIncompleteSummaries();

        return view('close_check.index', [
            'tasks' => $tasks,
            'staffList' => $staffList,
            'incompleteLines' => $incompleteLines,
        ]);
    }

    public function process(Request $request): RedirectResponse
    {
        $incomplete = app(RoutineInventoryCompletionService::class)->globalIncompleteSummaries();
        if (! empty($incomplete)) {
            abort(403, 'Inventaire non terminé. Veuillez terminer l’inventaire et les tâches avant de clôturer.');
        }

        $validated = $request->validate([
            'staff_id' => ['required', 'integer', 'exists:staff,id'],
            'pin_code' => ['required', 'string', 'digits:4'],
        ]);

        $staff = Staff::query()
            ->where('id', $validated['staff_id'])
            ->where('is_active', true)
            ->first();

        if (! $staff) {
            return redirect()
                ->route('close-check.index')
                ->withInput($request->except('pin_code'))
                ->with('error', 'Collaborateur introuvable ou inactif.');
        }

        if ($staff->pin_code === null || $staff->pin_code === '') {
            return redirect()
                ->route('close-check.index')
                ->withInput($request->except('pin_code'))
                ->with('error', 'Code PIN non enregistré. Contactez l’administrateur.');
        }

        $pinKey = 'close-check-pin:staff:'.$staff->id.':'.($request->ip() ?? 'unknown');

        if (RateLimiter::tooManyAttempts($pinKey, 5)) {
            return redirect()
                ->route('close-check.index')
                ->withInput($request->except('pin_code'))
                ->with('error', 'Trop de tentatives de code PIN. Réessayez plus tard.');
        }

        if (! hash_equals((string) $staff->pin_code, (string) $validated['pin_code'])) {
            RateLimiter::hit($pinKey, 60);

            return redirect()
                ->route('close-check.index')
                ->withInput($request->except('pin_code'))
                ->with('error', 'Code PIN incorrect.');
        }

        RateLimiter::clear($pinKey);

        CloseCheckLog::query()->create([
            'staff_id' => $staff->id,
            'date' => BusinessDate::toDateString(),
            'completed_at' => now(),
        ]);

        $businessDate = BusinessDate::toDateString();
        $lunchMissing = ShiftClockOutGate::missingClockOutStaffNames($businessDate, 'lunch');
        $dinnerMissing = ShiftClockOutGate::missingClockOutStaffNames($businessDate, 'dinner');
        $clockoutWarnings = array_values(array_unique(array_merge($lunchMissing, $dinnerMissing)));

        return redirect()
            ->route('close-check.success')
            ->with('closed_staff_name', $staff->name)
            ->with('close_check_clockout_warnings', $clockoutWarnings);
    }

    public function success(Request $request): View|RedirectResponse
    {
        if (! $request->session()->has('closed_staff_name')) {
            return redirect()->route('close-check.index');
        }

        $warnings = $request->session()->get('close_check_clockout_warnings', []);
        if (! is_array($warnings)) {
            $warnings = [];
        }

        return view('close_check.success', [
            'closedStaffName' => (string) $request->session()->get('closed_staff_name'),
            'clockoutWarnings' => $warnings,
            'businessDate' => BusinessDate::toDateString(),
            'whatsappDigits' => $this->whatsappManagerDigits(),
        ]);
    }

    /**
     * Chiffres uniquement pour wa.me (même logique que l’ancien bloc Filament).
     */
    private function whatsappManagerDigits(): string
    {
        $raw = config('services.whatsapp.manager_number');
        if ($raw === null || $raw === '') {
            return '';
        }

        return (string) preg_replace('/\D+/', '', (string) $raw);
    }
}
