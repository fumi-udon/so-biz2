<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\Staff;
use App\Models\User;
use App\Support\AttendanceLateCalculator;
use App\Support\BusinessDate;
use Filament\Notifications\Notification;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

final class TimecardPunchService
{
    /**
     * @var array<string, string>
     */
    private const ACTION_TO_COLUMN = [
        'lunch_in' => 'lunch_in_at',
        'lunch_out' => 'lunch_out_at',
        'dinner_in' => 'dinner_in_at',
        'dinner_out' => 'dinner_out_at',
    ];

    public function resolveTargetBusinessDate(): Carbon
    {
        return BusinessDate::current();
    }

    private function englishDayKey(Carbon $moment): string
    {
        return strtolower($moment->copy()->locale('en')->dayName);
    }

    /**
     * @param  'lunch'|'dinner'  $meal
     */
    public function isMealScheduled(Staff $staff, Carbon $businessDate, string $meal): bool
    {
        $shifts = $staff->fixed_shifts;

        if (! is_array($shifts)) {
            return false;
        }

        $dayKey = $this->englishDayKey($businessDate);
        $dayShifts = $shifts[$dayKey] ?? null;

        if (! is_array($dayShifts)) {
            return false;
        }

        $mealShift = $dayShifts[$meal] ?? null;
        $planned = is_array($mealShift) ? ($mealShift[0] ?? null) : null;

        return $planned !== null && $planned !== '';
    }

    /**
     * 打刻アクションに対応する食事の予定だけをスナップショットする。
     * ランチ打刻でディナー予定を固定しない（ディナー打刻前のシフト変更を反映するため）。
     *
     * @param  'lunch_in'|'lunch_out'|'dinner_in'|'dinner_out'  $punchAction
     */
    private function ensureScheduledSnapshotsForPunch(Attendance $attendance, Staff $staff, Carbon $businessDate, string $punchAction): void
    {
        $snaps = AttendanceLateCalculator::snapshotScheduledTimesFromFixedShifts($staff, $businessDate);

        $lane = match ($punchAction) {
            'lunch_in', 'lunch_out' => 'lunch',
            'dinner_in', 'dinner_out' => 'dinner',
            default => null,
        };

        if ($lane === 'lunch') {
            if ($attendance->scheduled_in_at === null && $this->isMealScheduled($staff, $businessDate, 'lunch')) {
                $attendance->scheduled_in_at = $snaps['scheduled_in_at'];
            }

            return;
        }

        if ($lane === 'dinner') {
            if ($attendance->scheduled_dinner_at === null && $this->isMealScheduled($staff, $businessDate, 'dinner')) {
                $attendance->scheduled_dinner_at = $snaps['scheduled_dinner_at'];
            }
        }
    }

    /**
     * @param  'lunch_in'|'lunch_out'|'dinner_in'|'dinner_out'  $action
     */
    public function processNormalPunch(Staff $staff, string $action): TimecardPunchOutcome
    {
        $targetDate = $this->resolveTargetBusinessDate();
        $dateString = $targetDate->toDateString();

        if ($action === 'lunch_in' && ! $this->isMealScheduled($staff, $targetDate, 'lunch')) {
            return new TimecardPunchOutcome(false, 'Aucun shift dejeuner prevu aujourd\'hui. Utilisez la demande d\'aide.');
        }

        if ($action === 'dinner_in' && ! $this->isMealScheduled($staff, $targetDate, 'dinner')) {
            return new TimecardPunchOutcome(false, 'Aucun shift diner prevu aujourd\'hui. Utilisez la demande d\'aide.');
        }

        $existing = Attendance::query()
            ->where('staff_id', $staff->id)
            ->where('date', $dateString)
            ->first();

        if ($action === 'lunch_out' && ($existing === null || $existing->lunch_in_at === null)) {
            return new TimecardPunchOutcome(false, 'Aucun pointage d\'entree dejeuner trouve.');
        }

        if ($action === 'dinner_out' && ($existing === null || $existing->dinner_in_at === null)) {
            return new TimecardPunchOutcome(false, 'Aucun pointage d\'entree diner trouve.');
        }

        if (in_array($action, ['lunch_out', 'dinner_out'], true)) {
            $gate = app(RoutineInventoryCompletionService::class);

            if (! $gate->staffHasAllRoutineAndInventoryDone($staff, $dateString)) {
                return new TimecardPunchOutcome(false, 'Des taches ou inventaires restent incomplets. Verifiez Mon espace.');
            }
        }

        $column = self::ACTION_TO_COLUMN[$action];
        $clockAt = now();

        $recordedLate = false;
        $lateDelta = 0;
        $tipAutoApplied = false;

        try {
            DB::transaction(function () use ($staff, $dateString, $column, $clockAt, $targetDate, $action, &$recordedLate, &$lateDelta, &$tipAutoApplied): void {
                $attendance = Attendance::query()->firstOrCreate(
                    [
                        'staff_id' => $staff->id,
                        'date' => $dateString,
                    ],
                    [
                        'late_minutes' => 0,
                        'is_tip_eligible' => false,
                        'is_edited_by_admin' => false,
                    ],
                );

                $attendance = Attendance::query()->whereKey($attendance->id)->lockForUpdate()->first();

                if ($attendance === null) {
                    throw new \RuntimeException('Impossible de traiter le pointage. Reessayez.');
                }

                if ($attendance->getAttribute($column) !== null) {
                    throw new \RuntimeException('Ce pointage est deja enregistre.');
                }

                $beforeLate = (int) ($attendance->late_minutes ?? 0);

                $this->ensureScheduledSnapshotsForPunch($attendance, $staff, $targetDate, $action);

                $attendance->{$column} = $clockAt;
                $attendance->late_minutes = AttendanceLateCalculator::totalLateMinutes($attendance);

                $lateDelta = max(0, $attendance->late_minutes - $beforeLate);
                $recordedLate = $attendance->late_minutes > $beforeLate;

                if ($action === 'lunch_in') {
                    $mealLate = AttendanceLateCalculator::lateMinutesForMeal(
                        $attendance->lunch_in_at,
                        $attendance->scheduled_in_at
                    );
                    if ($mealLate === 0) {
                        $attendance->is_lunch_tip_applied = true;
                        $tipAutoApplied = true;
                    }
                } elseif ($action === 'dinner_in') {
                    $mealLate = AttendanceLateCalculator::lateMinutesForMeal(
                        $attendance->dinner_in_at,
                        $attendance->scheduled_dinner_at
                    );
                    if ($mealLate === 0) {
                        $attendance->is_dinner_tip_applied = true;
                        $tipAutoApplied = true;
                    }
                }

                $attendance->save();
            });
        } catch (QueryException $e) {
            if ($this->isAttendanceDuplicateKeyException($e)) {
                return new TimecardPunchOutcome(false, 'Ce pointage est deja enregistre.');
            }

            throw $e;
        } catch (\RuntimeException $e) {
            return new TimecardPunchOutcome(false, $e->getMessage());
        }

        $this->notifyNormalPunch($staff, $action);

        $isClockIn = in_array($action, ['lunch_in', 'dinner_in'], true);

        if ($isClockIn) {
            if ($recordedLate && $lateDelta > 0) {
                return new TimecardPunchOutcome(true, null, 'mypage_late', $lateDelta, false);
            }

            return new TimecardPunchOutcome(true, null, 'mypage_success', null, $tipAutoApplied);
        }

        return new TimecardPunchOutcome(true, null, 'timecard_success');
    }

    /**
     * @param  'lunch'|'dinner'  $meal
     */
    public function processExtraShift(Staff $staff, string $meal, string $reason): TimecardPunchOutcome
    {
        $targetDate = $this->resolveTargetBusinessDate();
        $dateString = $targetDate->toDateString();
        $action = $meal === 'lunch' ? 'lunch_in' : 'dinner_in';
        $column = self::ACTION_TO_COLUMN[$action];

        if ($this->isMealScheduled($staff, $targetDate, $meal)) {
            return new TimecardPunchOutcome(false, 'Ce shift est deja planifie. Utilisez le bouton normal d\'entree.');
        }

        $clockAt = now();
        $reasonTrim = trim($reason);
        $shiftLabel = $meal === 'lunch' ? 'Dejeuner' : 'Diner';

        try {
            DB::transaction(function () use ($staff, $dateString, $column, $clockAt, $shiftLabel, $reasonTrim, $targetDate, $action): void {
                $attendance = Attendance::query()->firstOrCreate(
                    [
                        'staff_id' => $staff->id,
                        'date' => $dateString,
                    ],
                    [
                        'late_minutes' => 0,
                        'is_tip_eligible' => false,
                        'is_edited_by_admin' => false,
                    ],
                );

                $attendance = Attendance::query()->whereKey($attendance->id)->lockForUpdate()->first();

                if ($attendance === null) {
                    throw new \RuntimeException('Impossible de traiter le pointage. Reessayez.');
                }

                if ($attendance->getAttribute($column) !== null) {
                    throw new \RuntimeException('Ce pointage est deja enregistre.');
                }

                $this->ensureScheduledSnapshotsForPunch($attendance, $staff, $targetDate, $action);

                $attendance->{$column} = $clockAt;
                $attendance->late_minutes = AttendanceLateCalculator::totalLateMinutes($attendance);

                $line = sprintf(
                    '[%s] [Entree exceptionnelle] Aide %s%s',
                    $clockAt->format('Y-m-d H:i'),
                    $shiftLabel,
                    $reasonTrim !== '' ? ' Motif: '.$reasonTrim : ''
                );
                $prev = $attendance->in_note;
                $attendance->in_note = $prev !== null && $prev !== ''
                    ? trim($prev."\n".$line)
                    : $line;

                $attendance->save();
            });
        } catch (QueryException $e) {
            if ($this->isAttendanceDuplicateKeyException($e)) {
                return new TimecardPunchOutcome(false, 'Ce pointage est deja enregistre.');
            }

            throw $e;
        } catch (\RuntimeException $e) {
            return new TimecardPunchOutcome(false, $e->getMessage());
        }

        $adminUsers = User::all();
        Notification::make()
            ->title('[Entree exceptionnelle] '.$staff->name.' a ete ajoute(e). Verifiez le tip.')
            ->body($staff->name.' a rejoint l\'aide '.$shiftLabel.'. Merci de verifier le tip.'.($reasonTrim !== '' ? ' ('.$reasonTrim.')' : ''))
            ->warning()
            ->sendToDatabase($adminUsers);

        return new TimecardPunchOutcome(true, null, 'mypage_success');
    }

    /**
     * @param  'lunch_in'|'lunch_out'|'dinner_in'|'dinner_out'  $action
     */
    private function notifyNormalPunch(Staff $staff, string $action): void
    {
        $actionLabel = match ($action) {
            'lunch_in' => 'entree dejeuner',
            'lunch_out' => 'sortie dejeuner',
            'dinner_in' => 'entree diner',
            'dinner_out' => 'sortie diner',
            default => 'pointage',
        };

        $adminUsers = User::all();

        $notification = Notification::make()
            ->title("{$staff->name} a fait {$actionLabel}")
            ->success();

        $notification->sendToDatabase($adminUsers);

        try {
            $notification->broadcast($adminUsers);
        } catch (\Throwable) {
            // ブロードキャスト失敗時は DB 通知だけ確実に残す。
        }
    }

    private function isAttendanceDuplicateKeyException(QueryException $e): bool
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
