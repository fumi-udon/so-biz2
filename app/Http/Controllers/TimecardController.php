<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Setting;
use App\Models\Staff;
use App\Services\RoutineInventoryCompletionService;
use App\Support\BusinessDate;
use Illuminate\Database\QueryException;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;

class TimecardController extends Controller
{
    /**
     * @var array<string, string>
     */
    protected const ACTION_TO_COLUMN = [
        'lunch_in' => 'lunch_in_at',
        'lunch_out' => 'lunch_out_at',
        'dinner_in' => 'dinner_in_at',
        'dinner_out' => 'dinner_out_at',
    ];

    /**
     * Clé JSON du jour en anglais (ex. monday), alignée sur fixed_shifts.
     */
    protected function englishDayKey(Carbon $moment): string
    {
        return strtolower($moment->copy()->locale('en')->dayName);
    }

    /**
     * @return 'lunch_start'|'dinner_start'|null
     */
    protected function scheduledStartKeyForAction(string $action): ?string
    {
        return match ($action) {
            'lunch_in' => 'lunch_start',
            'dinner_in' => 'dinner_start',
            default => null,
        };
    }

    /**
     * Interprète une heure planifiée（fixed_shifts）を、打刻時刻の日付アンカーで解釈する。
     */
    protected function scheduledDateTimeToday(string $timeString, Carbon $clockAt): ?Carbon
    {
        return \App\Support\BusinessDate::parseTimeForBusinessDate($timeString, $clockAt);
    }

    /**
     * Retard pour un pointage entrée midi / soir : null = pas de règle applicable, 0 = à l'heure (≤ 10 min de tolérance), > 0 = minutes de retard depuis l'heure prévue.
     */
    protected function lateMinutesForClockIn(Staff $staff, string $action, Carbon $clockAt): ?int
    {
        $startKey = $this->scheduledStartKeyForAction($action);

        if ($startKey === null) {
            return null;
        }

        $shifts = $staff->fixed_shifts;

        if (! is_array($shifts)) {
            return null;
        }

        $dayKey = $this->englishDayKey($this->resolveTargetBusinessDate());
        $dayShifts = $shifts[$dayKey] ?? null;

        if (! is_array($dayShifts)) {
            return null;
        }

        $planned = $dayShifts[$startKey] ?? null;

        if ($planned === null || $planned === '') {
            return null;
        }

        $scheduledAt = $this->scheduledDateTimeToday((string) $planned, $clockAt);

        if ($scheduledAt === null) {
            return null;
        }

        $tolerance = Setting::getValue('late_tolerance_minutes', 10);
        $graceMinutes = is_numeric($tolerance) ? (int) $tolerance : 10;

        $graceEnd = $scheduledAt->copy()->addMinutes($graceMinutes);

        if ($clockAt->lessThanOrEqualTo($graceEnd)) {
            return 0;
        }

        return (int) $scheduledAt->diffInMinutes($clockAt);
    }

    /**
     * Date d'activité (business day) : avant 6 h, la veille ; sinon jour courant (fuseau de l'application).
     */
    protected function resolveTargetBusinessDate(): Carbon
    {
        return BusinessDate::current();
    }

    public function index(): View
    {
        $staff = Staff::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return view('timecard.index', [
            'staff' => $staff,
            'targetBusinessDate' => $this->resolveTargetBusinessDate(),
        ]);
    }

    public function process(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'staff_id' => ['required', 'exists:staff,id'],
            'pin_code' => ['required', 'string', 'digits:4'],
            'action' => ['required', 'in:lunch_in,lunch_out,dinner_in,dinner_out'],
        ]);

        $staff = Staff::query()
            ->where('id', $validated['staff_id'])
            ->where('is_active', true)
            ->first();

        if (! $staff) {
            return redirect()
                ->back()
                ->withInput($request->except('pin_code'))
                ->with('error', 'Employé introuvable ou inactif.');
        }

        if ($staff->pin_code === null || $staff->pin_code === '') {
            return redirect()
                ->back()
                ->withInput($request->except('pin_code'))
                ->with('error', 'Aucun code PIN défini. Contactez un responsable.');
        }

        $pinKey = 'pin-attempt:'.$staff->id;

        if (RateLimiter::tooManyAttempts($pinKey, 5)) {
            return redirect()
                ->back()
                ->withInput($request->except('pin_code'))
                ->with('error', 'PINの入力を複数回間違えました。1分間お待ちください。');
        }

        if (! hash_equals((string) $staff->pin_code, (string) $validated['pin_code'])) {
            RateLimiter::hit($pinKey, 60);

            return redirect()
                ->back()
                ->withInput($request->except('pin_code'))
                ->with('error', 'Code PIN incorrect.');
        }

        RateLimiter::clear($pinKey);

        $targetDate = $this->resolveTargetBusinessDate();
        $dateString = $targetDate->toDateString();

        // 前日の未退勤があっても翌営業日の出勤打刻はブロックしない（未対応のブロックはここにない）。

        if ($validated['action'] === 'dinner_out') {
            $gate = app(RoutineInventoryCompletionService::class);

            if (! $gate->staffHasAllRoutineAndInventoryDone($staff, $dateString)) {
                return redirect()
                    ->back()
                    ->withInput($request->except('pin_code'))
                    ->with('error', '未完了のタスク・棚卸しがあります。マイページを確認してください。');
            }
        }
        $column = self::ACTION_TO_COLUMN[$validated['action']];
        $clockAt = now();

        $lateDelta = $this->lateMinutesForClockIn($staff, $validated['action'], $clockAt);

        $recordedLate = false;

        try {
            DB::transaction(function () use ($request, $staff, $dateString, $column, $clockAt, $lateDelta, &$recordedLate): void {
                $attendance = Attendance::query()
                    ->where('staff_id', $staff->id)
                    ->whereDate('date', $dateString)
                    ->lockForUpdate()
                    ->first();

                if (! $attendance) {
                    $attendance = new Attendance;
                    $attendance->staff_id = $staff->id;
                    $attendance->date = $dateString;
                    $attendance->late_minutes = 0;
                    $attendance->is_tip_eligible = false;
                    $attendance->is_edited_by_admin = false;
                } elseif ($attendance->getAttribute($column) !== null) {
                    throw new HttpResponseException(
                        redirect()
                            ->back()
                            ->withInput($request->except('pin_code'))
                            ->with('error', '既に打刻済みです。')
                    );
                }

                $attendance->{$column} = $clockAt;

                if ($lateDelta !== null && $lateDelta > 0) {
                    $attendance->late_minutes = (int) ($attendance->late_minutes ?? 0) + $lateDelta;
                    $recordedLate = true;
                }

                $attendance->save();
            });
        } catch (QueryException $e) {
            if ($this->isAttendanceDuplicateKeyException($e)) {
                return redirect()
                    ->back()
                    ->withInput($request->except('pin_code'))
                    ->with('error', '既に打刻済みです。');
            }

            throw $e;
        }

        $isClockIn = in_array($validated['action'], ['lunch_in', 'dinner_in'], true);

        if ($isClockIn) {
            if ($recordedLate) {
                return redirect()
                    ->route('mypage.index', ['staff_id' => $staff->id])
                    ->with('late_modal', true)
                    ->with('late_minutes', $lateDelta);
            }

            return redirect()
                ->route('mypage.index', ['staff_id' => $staff->id])
                ->with('success_modal', true);
        }

        return redirect()
            ->route('timecard.index')
            ->with('success_modal', true);
    }

    protected function isAttendanceDuplicateKeyException(QueryException $e): bool
    {
        if (($e->errorInfo[0] ?? null) === '23505') {
            return true;
        }

        if (isset($e->errorInfo[1]) && (int) $e->errorInfo[1] === 1062) {
            return true;
        }

        $msg = $e->getMessage();

        return str_contains($msg, 'Duplicate entry')
            || str_contains($msg, 'UNIQUE constraint failed')
            || str_contains($msg, 'duplicate key value violates unique constraint');
    }
}
