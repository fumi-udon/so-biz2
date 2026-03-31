<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\Setting;
use App\Models\Staff;
use App\Models\User;
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

    private function scheduledDateTimeToday(string $timeString, Carbon $clockAt): ?Carbon
    {
        return BusinessDate::parseTimeForBusinessDate($timeString, $clockAt);
    }

    private function lateMinutesForClockIn(Staff $staff, string $action, Carbon $clockAt): ?int
    {
        $mealKey = match ($action) {
            'lunch_in' => 'lunch',
            'dinner_in' => 'dinner',
            default => null,
        };

        if ($mealKey === null) {
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

        $mealShift = $dayShifts[$mealKey] ?? null;
        $planned = is_array($mealShift) ? ($mealShift[0] ?? null) : null;

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
     * @param  'lunch_in'|'lunch_out'|'dinner_in'|'dinner_out'  $action
     */
    public function processNormalPunch(Staff $staff, string $action): TimecardPunchOutcome
    {
        $targetDate = $this->resolveTargetBusinessDate();
        $dateString = $targetDate->toDateString();

        if ($action === 'lunch_in' && ! $this->isMealScheduled($staff, $targetDate, 'lunch')) {
            return new TimecardPunchOutcome(false, '本日のランチ予定がありません。臨時出勤（ヘルプ）申請から打刻してください。');
        }

        if ($action === 'dinner_in' && ! $this->isMealScheduled($staff, $targetDate, 'dinner')) {
            return new TimecardPunchOutcome(false, '本日のディナー予定がありません。臨時出勤（ヘルプ）申請から打刻してください。');
        }

        $existing = Attendance::query()
            ->where('staff_id', $staff->id)
            ->where('date', $dateString)
            ->first();

        if ($action === 'lunch_out' && ($existing === null || $existing->lunch_in_at === null)) {
            return new TimecardPunchOutcome(false, 'ランチ出勤の打刻がありません。');
        }

        if ($action === 'dinner_out' && ($existing === null || $existing->dinner_in_at === null)) {
            return new TimecardPunchOutcome(false, 'ディナー出勤の打刻がありません。');
        }

        if (in_array($action, ['lunch_out', 'dinner_out'], true)) {
            $gate = app(RoutineInventoryCompletionService::class);

            if (! $gate->staffHasAllRoutineAndInventoryDone($staff, $dateString)) {
                return new TimecardPunchOutcome(false, '未完了のタスク・棚卸しがあります。マイページを確認してください。');
            }
        }

        $column = self::ACTION_TO_COLUMN[$action];
        $clockAt = now();
        $lateDelta = $this->lateMinutesForClockIn($staff, $action, $clockAt);

        $recordedLate = false;

        try {
            DB::transaction(function () use ($staff, $dateString, $column, $clockAt, $lateDelta, &$recordedLate): void {
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
                    throw new \RuntimeException('打刻を処理できませんでした。もう一度お試しください。');
                }

                if ($attendance->getAttribute($column) !== null) {
                    throw new \RuntimeException('既に打刻済みです。');
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
                return new TimecardPunchOutcome(false, '既に打刻済みです。');
            }

            throw $e;
        } catch (\RuntimeException $e) {
            return new TimecardPunchOutcome(false, $e->getMessage());
        }

        $this->notifyNormalPunch($staff, $action);

        $isClockIn = in_array($action, ['lunch_in', 'dinner_in'], true);

        if ($isClockIn) {
            if ($recordedLate) {
                return new TimecardPunchOutcome(true, null, 'mypage_late', $lateDelta);
            }

            return new TimecardPunchOutcome(true, null, 'mypage_success');
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
            return new TimecardPunchOutcome(false, 'このシフトは予定に含まれています。通常の出勤ボタンから打刻してください。');
        }

        $clockAt = now();
        $reasonTrim = trim($reason);
        $shiftLabel = $meal === 'lunch' ? 'ランチ' : 'ディナー';

        try {
            DB::transaction(function () use ($staff, $dateString, $column, $clockAt, $shiftLabel, $reasonTrim): void {
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
                    throw new \RuntimeException('打刻を処理できませんでした。もう一度お試しください。');
                }

                if ($attendance->getAttribute($column) !== null) {
                    throw new \RuntimeException('既に打刻済みです。');
                }

                $attendance->{$column} = $clockAt;

                $line = sprintf(
                    '[%s] 【臨時出勤】%s ヘルプ打刻%s',
                    $clockAt->format('Y-m-d H:i'),
                    $shiftLabel,
                    $reasonTrim !== '' ? ' 理由: '.$reasonTrim : ''
                );
                $prev = $attendance->in_note;
                $attendance->in_note = $prev !== null && $prev !== ''
                    ? trim($prev."\n".$line)
                    : $line;

                $attendance->save();
            });
        } catch (QueryException $e) {
            if ($this->isAttendanceDuplicateKeyException($e)) {
                return new TimecardPunchOutcome(false, '既に打刻済みです。');
            }

            throw $e;
        } catch (\RuntimeException $e) {
            return new TimecardPunchOutcome(false, $e->getMessage());
        }

        $adminUsers = User::all();
        Notification::make()
            ->title('【臨時出勤】'.$staff->name.' が臨時出勤しました。チップ追加を確認してください。')
            ->body($staff->name.' が '.$shiftLabel.' のヘルプに入りました。チップの確認をお願いします。'.($reasonTrim !== '' ? '（'.$reasonTrim.'）' : ''))
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
            'lunch_in' => 'ランチ出勤',
            'lunch_out' => 'ランチ退勤',
            'dinner_in' => 'ディナー出勤',
            'dinner_out' => 'ディナー退勤',
            default => '打刻',
        };

        $adminUsers = User::all();

        $notification = Notification::make()
            ->title("{$staff->name} さんが{$actionLabel}しました")
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
