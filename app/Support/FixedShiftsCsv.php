<?php

namespace App\Support;

/**
 * fixed_shifts JSON と CSV フラット列（monday_lunch_start 等）の相互変換。
 *
 * @phpstan-type DayKey 'monday'|'tuesday'|'wednesday'|'thursday'|'friday'|'saturday'|'sunday'
 * @phpstan-type SlotKey 'lunch_start'|'lunch_end'|'dinner_start'|'dinner_end'
 */
final class FixedShiftsCsv
{
    /** @var list<DayKey> */
    public const DAYS = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];

    /** @var list<SlotKey> */
    public const SLOTS = ['lunch_start', 'lunch_end', 'dinner_start', 'dinner_end'];

    /**
     * @return list<string> 例: monday_lunch_start
     */
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

    /**
     * @param  array<string, mixed>|null  $fixedShifts
     * @return array<string, string|null>
     */
    public static function flatten(?array $fixedShifts): array
    {
        $out = [];

        foreach (self::DAYS as $day) {
            foreach (self::SLOTS as $slot) {
                $key = "{$day}_{$slot}";
                $v = null;

                if (is_array($fixedShifts) && isset($fixedShifts[$day]) && is_array($fixedShifts[$day])) {
                    $v = $fixedShifts[$day][$slot] ?? null;
                }
                if ($v === null || $v === '') {
                    $out[$key] = null;
                } else {
                    $out[$key] = is_string($v) ? $v : (string) $v;
                }
            }
        }

        return $out;
    }

    /**
     * フラット列名（例: monday_lunch_start）から data_get 用パス（monday.lunch_start）へ。
     */
    public static function dataGetPathFromFlatColumn(string $column): ?string
    {
        foreach (self::DAYS as $day) {
            $prefix = $day.'_';

            if (! str_starts_with($column, $prefix)) {
                continue;
            }

            $slot = substr($column, strlen($prefix));

            if (in_array($slot, self::SLOTS, true)) {
                return "{$day}.{$slot}";
            }
        }

        return null;
    }

    /**
     * フラット配列から fixed_shifts ツリーへ（空の日は省略可）。
     *
     * @param  array<string, mixed>  $flat
     * @return array<string, array<string, string|null>>|null
     */
    public static function expand(array $flat): ?array
    {
        $tree = [];

        foreach (self::DAYS as $day) {
            $dayRow = [];

            foreach (self::SLOTS as $slot) {
                $col = "{$day}_{$slot}";
                if (! array_key_exists($col, $flat)) {
                    $dayRow[$slot] = null;

                    continue;
                }

                $raw = $flat[$col];

                if ($raw === null || $raw === '') {
                    $dayRow[$slot] = null;
                } else {
                    $dayRow[$slot] = is_string($raw) ? trim($raw) : (string) $raw;
                }
            }

            if (self::dayHasAnyTime($dayRow)) {
                $tree[$day] = $dayRow;
            }
        }

        return $tree === [] ? null : $tree;
    }

    /**
     * @param  array<string, string|null>  $dayRow
     */
    private static function dayHasAnyTime(array $dayRow): bool
    {
        foreach ($dayRow as $v) {
            if ($v !== null && $v !== '') {
                return true;
            }
        }

        return false;
    }
}
