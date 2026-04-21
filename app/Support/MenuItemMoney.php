<?php

namespace App\Support;

/**
 * “ミリウム” (1/1000 DT) 保存 + 0.5 DT 刻みの入出力（管理画面・ゲスト表示用）。
 */
final class MenuItemMoney
{
    public const int MILLIEME_PER_DT = 1000;

    public const int MINOR_STEP = 500;

    public static function snapMinorToHalfDt(int $minor): int
    {
        $minor = max(0, $minor);

        return (int) (round($minor / self::MINOR_STEP) * self::MINOR_STEP);
    }

    /**
     * リピーター保存時: フォームの TextInput は既に {@see parseDtInputToMinor} で int (ミリウム) になった後、
     * mutate で再度数値に対して {@see parseDtInputToMinor} すると「14000」が 14000DT と誤解釈される。
     * 文字列（DT 入力）なら parse、既に int/float（ミリウム）なら 0.5 刻みスナップのみ。
     */
    public static function normalizePersistedOptionMinor(mixed $value): int
    {
        if ($value === null || $value === '' || is_bool($value)) {
            return 0;
        }
        if (is_string($value)) {
            return self::parseDtInputToMinor($value);
        }
        if (is_int($value) || is_float($value)) {
            if (is_float($value) && ! is_finite($value)) {
                return 0;
            }

            return self::snapMinorToHalfDt((int) round($value));
        }

        return 0;
    }

    /**
     * フォーム・API 文字列 → 保存用ミリウム（0.5 DT 刻みにスナップ）。
     */
    public static function parseDtInputToMinor(mixed $value): int
    {
        if ($value === null || $value === '') {
            return 0;
        }
        if (is_int($value) || is_float($value)) {
            $dt = (float) $value;
        } else {
            $s = trim((string) $value);
            if ($s === '') {
                return 0;
            }
            $s = (string) (preg_replace('/\s*dt\s*$/i', '', $s) ?? $s);
            $s = str_replace(',', '.', $s);
            if ($s === '' || $s === '.') {
                return 0;
            }
            $dt = (float) $s;
        }
        if (! is_finite($dt) || $dt < 0) {
            return 0;
        }
        $halfSteps = (int) round($dt * 2);

        return $halfSteps * self::MINOR_STEP;
    }

    /**
     * 保存値 → フォーム用（12 / 12.5、単位なし）
     */
    public static function minorToDtInputString(int $minor): string
    {
        $minor = self::snapMinorToHalfDt($minor);
        if ($minor % self::MILLIEME_PER_DT === 0) {
            return (string) intdiv($minor, self::MILLIEME_PER_DT);
        }
        $whole = intdiv($minor, self::MILLIEME_PER_DT);

        return $whole.'.5';
    }

    /**
     * 保存値 → 人間可読（例: 12 DT, 12.5 DT, 0.5 DT）
     */
    public static function formatMinorForDisplay(int $minor): string
    {
        $minor = self::snapMinorToHalfDt($minor);
        if ($minor % self::MILLIEME_PER_DT === 0) {
            return (string) intdiv($minor, self::MILLIEME_PER_DT).' DT';
        }
        $whole = intdiv($minor, self::MILLIEME_PER_DT);

        return $whole.'.5 DT';
    }
}
