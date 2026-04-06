<?php

namespace App\Support;

use App\Models\Attendance;
use App\Models\Setting;
use App\Models\Staff;
use Illuminate\Support\Carbon;

/**
 * 遅刻分数は「保存済みの予定出勤スナップショット」のみを基準とする（fixed_shifts の生参照はしない）。
 */
final class AttendanceLateCalculator
{
    /**
     * 指定営業日の fixed_shifts から、ランチ／ディナー予定開始を Carbon で返す（スナップショット用）。
     *
     * @return array{scheduled_in_at: ?Carbon, scheduled_dinner_at: ?Carbon}
     */
    public static function snapshotScheduledTimesFromFixedShifts(Staff $staff, Carbon $businessDate): array
    {
        $dayKey = strtolower($businessDate->copy()->locale('en')->dayName);
        $lunchStr = FixedShiftSchedule::start($staff, $dayKey, 'lunch');
        $dinnerStr = FixedShiftSchedule::start($staff, $dayKey, 'dinner');

        $lunchAt = ($lunchStr !== null && $lunchStr !== '')
            ? BusinessDate::parseTimeForBusinessDate($lunchStr, $businessDate->copy()->startOfDay())
            : null;
        $dinnerAt = ($dinnerStr !== null && $dinnerStr !== '')
            ? BusinessDate::parseTimeForBusinessDate($dinnerStr, $businessDate->copy()->startOfDay())
            : null;

        return [
            'scheduled_in_at' => $lunchAt,
            'scheduled_dinner_at' => $dinnerAt,
        ];
    }

    /**
     * 1 区間の遅刻分。予定が無い・打刻が無い場合は 0（判定不能として過去改ざんを防ぐ）。
     */
    public static function lateMinutesForMeal(?Carbon $clockIn, ?Carbon $scheduledStart): int
    {
        if ($clockIn === null || $scheduledStart === null) {
            return 0;
        }

        $tolerance = Setting::getValue('late_tolerance_minutes', 10);
        $graceMins = is_numeric($tolerance) ? (int) $tolerance : 10;
        $graceEnd = $scheduledStart->copy()->addMinutes($graceMins);

        if ($clockIn->lessThanOrEqualTo($graceEnd)) {
            return 0;
        }

        return (int) $scheduledStart->diffInMinutes($clockIn);
    }

    /**
     * ランチ・ディナーの遅刻分を合算（DB の late_minutes と一致させる用途）。
     */
    public static function totalLateMinutes(Attendance $attendance): int
    {
        return self::lateMinutesForMeal($attendance->lunch_in_at, $attendance->scheduled_in_at)
            + self::lateMinutesForMeal($attendance->dinner_in_at, $attendance->scheduled_dinner_at);
    }

    /**
     * Filament 保存直前の配列から合計遅刻を算出（正規化済みの時刻を想定）。
     *
     * @param  array<string, mixed>  $data
     */
    public static function totalLateMinutesFromNormalizedData(array $data): int
    {
        $lunchIn = $data['lunch_in_at'] ?? null;
        $dinnerIn = $data['dinner_in_at'] ?? null;
        $schedL = $data['scheduled_in_at'] ?? null;
        $schedD = $data['scheduled_dinner_at'] ?? null;

        $lunchIn = $lunchIn instanceof Carbon ? $lunchIn : ($lunchIn ? Carbon::parse($lunchIn) : null);
        $dinnerIn = $dinnerIn instanceof Carbon ? $dinnerIn : ($dinnerIn ? Carbon::parse($dinnerIn) : null);
        $schedL = $schedL instanceof Carbon ? $schedL : ($schedL ? Carbon::parse($schedL) : null);
        $schedD = $schedD instanceof Carbon ? $schedD : ($schedD ? Carbon::parse($schedD) : null);

        return self::lateMinutesForMeal($lunchIn, $schedL)
            + self::lateMinutesForMeal($dinnerIn, $schedD);
    }
}
