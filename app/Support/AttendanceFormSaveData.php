<?php

namespace App\Support;

use App\Models\Attendance;
use Illuminate\Support\Carbon;

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
        foreach (['lunch_in_at', 'lunch_out_at', 'dinner_in_at', 'dinner_out_at'] as $field) {
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
}
