<?php

namespace App\Support;

/**
 * fixed_shifts JSON 配列形式 と CSV フラット列の相互変換。
 */
final class FixedShiftsCsv
{
    public const DAYS = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];

    public const SLOTS = ['lunch_start', 'lunch_end', 'dinner_start', 'dinner_end'];

    public static function flatColumnNames(): array
    {
        $names = [];
        foreach (self::DAYS as $day) {
            foreach (self::SLOTS as $slot) {
                $names[] = "{$day}_{$slot}";
            }
        }

        return $names;
    }

    public static function dataGetPathFromFlatColumn(string $column): ?string
    {
        $map = [
            'lunch_start' => 'lunch.0',
            'lunch_end' => 'lunch.1',
            'dinner_start' => 'dinner.0',
            'dinner_end' => 'dinner.1',
        ];

        foreach (self::DAYS as $day) {
            $prefix = $day.'_';
            if (! str_starts_with($column, $prefix)) {
                continue;
            }
            $slot = substr($column, strlen($prefix));
            if (isset($map[$slot])) {
                return "{$day}.{$map[$slot]}";
            }
        }

        return null;
    }

    public static function flatten(?array $fixedShifts): array
    {
        $out = [];
        $map = [
            'lunch_start' => ['lunch', 0],
            'lunch_end' => ['lunch', 1],
            'dinner_start' => ['dinner', 0],
            'dinner_end' => ['dinner', 1],
        ];

        foreach (self::DAYS as $day) {
            foreach (self::SLOTS as $slot) {
                $key = "{$day}_{$slot}";
                $v = null;
                if (is_array($fixedShifts) && isset($fixedShifts[$day]) && is_array($fixedShifts[$day])) {
                    $meal = $map[$slot][0];
                    $idx = $map[$slot][1];
                    if (isset($fixedShifts[$day][$meal]) && is_array($fixedShifts[$day][$meal])) {
                        $v = $fixedShifts[$day][$meal][$idx] ?? null;
                    }
                }
                $out[$key] = ($v === null || $v === '') ? null : (string) $v;
            }
        }

        return $out;
    }

    public static function expand(array $flat): ?array
    {
        $tree = [];
        foreach (self::DAYS as $day) {
            $lunchStart = $flat["{$day}_lunch_start"] ?? null;
            $lunchEnd = $flat["{$day}_lunch_end"] ?? null;
            $dinnerStart = $flat["{$day}_dinner_start"] ?? null;
            $dinnerEnd = $flat["{$day}_dinner_end"] ?? null;

            $lunch = ($lunchStart === null && $lunchEnd === null) ? null : [
                is_string($lunchStart) ? trim($lunchStart) : (string) $lunchStart,
                is_string($lunchEnd) ? trim($lunchEnd) : (string) $lunchEnd,
            ];
            $dinner = ($dinnerStart === null && $dinnerEnd === null) ? null : [
                is_string($dinnerStart) ? trim($dinnerStart) : (string) $dinnerStart,
                is_string($dinnerEnd) ? trim($dinnerEnd) : (string) $dinnerEnd,
            ];

            if ($lunch !== null || $dinner !== null) {
                $tree[$day] = [
                    'lunch' => $lunch,
                    'dinner' => $dinner,
                ];
            }
        }

        return $tree === [] ? null : $tree;
    }
}
