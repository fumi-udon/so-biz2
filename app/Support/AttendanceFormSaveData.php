<?php

namespace App\Support;

use App\Models\Attendance;
use App\Models\Staff;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * 勤怠フォーム保存時に、打刻時刻をレコードの営業日（date）と結合して正規化する。
 * DateTimePicker が別日付を送っても上書きしないよう、時刻部分のみを取り出して再構築する。
 */
final class AttendanceFormSaveData
{
    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function normalizeForRecord(Attendance $record, array $data): array
    {
        $businessDate = $record->date instanceof Carbon
            ? $record->date->copy()->startOfDay()
            : Carbon::parse($record->date)->startOfDay();

        return self::normalizeClockAttributes($data, $businessDate);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function normalizeForCreate(array $data): array
    {
        $businessDate = isset($data['date'])
            ? Carbon::parse($data['date'])->startOfDay()
            : BusinessDate::current();

        return self::normalizeClockAttributes($data, $businessDate);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private static function normalizeClockAttributes(array $data, Carbon $businessDate): array
    {
        foreach (['lunch_in_at', 'lunch_out_at', 'dinner_in_at', 'dinner_out_at', 'scheduled_in_at', 'scheduled_dinner_at'] as $field) {
            if (! array_key_exists($field, $data)) {
                continue;
            }

            $raw = $data[$field];
            if ($raw === null || $raw === '') {
                $data[$field] = null;

                continue;
            }

            $timeStr = $raw instanceof Carbon
                ? $raw->format('H:i')
                : Carbon::parse($raw)->format('H:i');

            $data[$field] = BusinessDate::parseTimeForBusinessDate($timeStr, $businessDate);
        }

        return $data;
    }

    /**
     * ランチ・ディナーいずれかの入店打刻が必須（両方 null は保存不可）。
     *
     * @param  array<string, mixed>  $data
     */
    public static function assertAtLeastOneMealClockIn(array $data): void
    {
        $lunch = $data['lunch_in_at'] ?? null;
        $dinner = $data['dinner_in_at'] ?? null;

        if ($lunch === null && $dinner === null) {
            throw ValidationException::withMessages([
                'lunch_in_at' => 'ランチまたはディナーのいずれかの入店時刻を入力してください。',
            ]);
        }
    }

    /**
     * 予定出勤スナップショットを埋め、遅刻分を再計算する。
     * ランチ／ディナーは食事単位で分離（実効入店がない食事の予定は自動投入しない。手入力があれば尊重）。
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function finalizeForSave(array $data, ?Attendance $existing = null): array
    {
        $staffId = $data['staff_id'] ?? null;
        if (! $staffId || empty($data['date'])) {
            $data['late_minutes'] = AttendanceLateCalculator::totalLateMinutesFromNormalizedData($data);

            return $data;
        }

        $staff = Staff::query()->find($staffId);
        if (! $staff) {
            $data['late_minutes'] = AttendanceLateCalculator::totalLateMinutesFromNormalizedData($data);

            return $data;
        }

        $businessDate = Carbon::parse($data['date'])->startOfDay();
        $snaps = AttendanceLateCalculator::snapshotScheduledTimesFromFixedShifts($staff, $businessDate);

        $incoming = $data;
        $hasKeyLunchSched = array_key_exists('scheduled_in_at', $incoming);
        $hasKeyDinnerSched = array_key_exists('scheduled_dinner_at', $incoming);
        $incomingExplicitLunchSched = $hasKeyLunchSched && ! self::isUnsetOrEmptyDatetime($incoming['scheduled_in_at'] ?? null);
        $incomingExplicitDinnerSched = $hasKeyDinnerSched && ! self::isUnsetOrEmptyDatetime($incoming['scheduled_dinner_at'] ?? null);

        if ($existing !== null) {
            if (! $hasKeyLunchSched) {
                $data['scheduled_in_at'] = $existing->scheduled_in_at;
            }
            if (! $hasKeyDinnerSched) {
                $data['scheduled_dinner_at'] = $existing->scheduled_dinner_at;
            }
        }

        $effLunch = self::effectiveMealClockIn($existing, $data, 'lunch_in_at');
        $effDinner = self::effectiveMealClockIn($existing, $data, 'dinner_in_at');

        $staffOrDateChanged = $existing !== null && (
            (int) $existing->staff_id !== (int) $staffId
            || $existing->date->toDateString() !== $businessDate->toDateString()
        );

        if (! $incomingExplicitLunchSched) {
            if ($effLunch === null) {
                $data['scheduled_in_at'] = null;
            } elseif ($staffOrDateChanged) {
                $data['scheduled_in_at'] = $snaps['scheduled_in_at'];
            } elseif (self::isUnsetOrEmptyDatetime($data['scheduled_in_at'] ?? null)) {
                $data['scheduled_in_at'] = $snaps['scheduled_in_at'];
            }
        }

        if (! $incomingExplicitDinnerSched) {
            if ($effDinner === null) {
                $data['scheduled_dinner_at'] = null;
            } elseif ($staffOrDateChanged) {
                $data['scheduled_dinner_at'] = $snaps['scheduled_dinner_at'];
            } elseif (self::isUnsetOrEmptyDatetime($data['scheduled_dinner_at'] ?? null)) {
                $data['scheduled_dinner_at'] = $snaps['scheduled_dinner_at'];
            }
        }

        $data = self::normalizeClockAttributes($data, $businessDate);
        $data['late_minutes'] = AttendanceLateCalculator::totalLateMinutesFromNormalizedData($data);

        return $data;
    }

    /**
     * フォーム優先。キーが無いときは既存レコードの値を参照（編集時）。
     */
    private static function effectiveMealClockIn(?Attendance $existing, array $data, string $field): ?Carbon
    {
        if (array_key_exists($field, $data)) {
            $v = $data[$field];
            if ($v === null || $v === '') {
                return null;
            }

            return $v instanceof Carbon ? $v : Carbon::parse($v);
        }

        if ($existing === null) {
            return null;
        }

        return $existing->{$field};
    }

    private static function isUnsetOrEmptyDatetime(mixed $v): bool
    {
        return $v === null || $v === '';
    }
}
