<?php

namespace App\Services;

use App\Models\Attendance;
use App\Support\BusinessDate;
use App\Support\FixedShiftSchedule;
use App\Support\ShiftClockOutGate;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * 退勤打刻漏れの自動補完サービス。
 *
 * 深夜バッチ（app:auto-clock-out）からのみ呼ばれる。
 * CloseCheck（レジ締め）には一切組み込まない。
 *
 * ペナルティルール: 「シフト予定退勤 - N分」を自動退勤時刻とする。
 * 計算結果が入店時刻より前になる場合は入店時刻でクランプ（負の勤務時間を防止）。
 *
 * 安全設計:
 * - シフト終了時刻が未定義 → スキップ
 * - 時刻文字列パース失敗 → スキップ
 * - DB 更新中の例外 → スキップ（バッチ全体を落とさない）
 * - 冪等: すでに退勤が記録済みの行はスキップ
 * - 行単位の lockForUpdate でトランザクションを保護
 */
final class AutoClockOutService
{
    public function runForDate(Carbon $businessDate): AutoClockOutResult
    {
        $dateString = $businessDate->toDateString();
        $dayKey = strtolower($businessDate->copy()->locale('en')->dayName);
        $penaltyMinutes = max(0, (int) config('timecard.auto_clock_out_penalty_minutes', 30));

        $filled = [];
        $skipped = [];

        foreach (['lunch', 'dinner'] as $meal) {
            /** @var Collection<int, Attendance> $attendances */
            $attendances = ShiftClockOutGate::missingClockOutAttendances($dateString, $meal);

            foreach ($attendances as $attendance) {
                $staff = $attendance->staff;

                if ($staff === null) {
                    continue;
                }

                // シフト終了時刻を取得（null ならスキップ）
                $endStr = FixedShiftSchedule::end($staff, $dayKey, $meal);

                if ($endStr === null) {
                    $skipped[] = [
                        'staff_name' => $staff->name,
                        'meal' => $meal,
                        'reason' => 'shift_end_undefined',
                    ];
                    Log::warning('AutoClockOut: シフト終了未定義のためスキップ', [
                        'staff_id' => $staff->id,
                        'staff_name' => $staff->name,
                        'meal' => $meal,
                        'date' => $dateString,
                    ]);

                    continue;
                }

                // 時刻文字列を営業日基準の Carbon に変換（パース失敗ならスキップ）
                $plannedEnd = BusinessDate::parseTimeForBusinessDate($endStr, $businessDate->copy()->startOfDay());

                if ($plannedEnd === null) {
                    $skipped[] = [
                        'staff_name' => $staff->name,
                        'meal' => $meal,
                        'reason' => 'shift_end_parse_error',
                    ];
                    Log::warning('AutoClockOut: シフト終了時刻のパース失敗', [
                        'staff_id' => $staff->id,
                        'staff_name' => $staff->name,
                        'meal' => $meal,
                        'date' => $dateString,
                        'raw_end' => $endStr,
                    ]);

                    continue;
                }

                // ペナルティ適用: 予定退勤 - N分
                $computedOut = $plannedEnd->copy()->subMinutes($penaltyMinutes);

                // クランプ: 入店より前にならないよう保護
                $mealInAt = $meal === 'lunch'
                    ? $attendance->lunch_in_at
                    : $attendance->dinner_in_at;

                if ($mealInAt !== null && $computedOut->lessThan($mealInAt)) {
                    $computedOut = $mealInAt->copy();
                }

                $outColumn = $meal === 'lunch' ? 'lunch_out_at' : 'dinner_out_at';
                $flagColumn = $meal === 'lunch' ? 'is_lunch_auto_clocked_out' : 'is_dinner_auto_clocked_out';

                try {
                    $wasUpdated = false;

                    DB::transaction(function () use ($attendance, $outColumn, $flagColumn, $computedOut, &$wasUpdated): void {
                        /** @var Attendance|null $locked */
                        $locked = Attendance::query()
                            ->whereKey($attendance->id)
                            ->lockForUpdate()
                            ->first();

                        if ($locked === null) {
                            return;
                        }

                        // 冪等: すでに退勤が入っていれば何もしない
                        if ($locked->getAttribute($outColumn) !== null) {
                            return;
                        }

                        $locked->{$outColumn} = $computedOut;
                        $locked->{$flagColumn} = true;
                        $locked->save();

                        $wasUpdated = true;
                    });

                    if ($wasUpdated) {
                        $filled[] = [
                            'staff_name' => $staff->name,
                            'meal' => $meal,
                            'out_at' => $computedOut->format('H:i'),
                        ];
                    }
                } catch (\Throwable $e) {
                    $skipped[] = [
                        'staff_name' => $staff->name,
                        'meal' => $meal,
                        'reason' => 'db_error',
                    ];
                    Log::error('AutoClockOut: DB更新失敗', [
                        'staff_id' => $staff->id,
                        'meal' => $meal,
                        'date' => $dateString,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        return new AutoClockOutResult($filled, $skipped);
    }
}
