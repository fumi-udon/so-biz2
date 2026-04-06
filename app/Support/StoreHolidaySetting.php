<?php

namespace App\Support;

use App\Models\Setting;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * 店舗休業日（ヴァカンス）を Setting の JSON 配列として保持する。
 */
final class StoreHolidaySetting
{
    public const KEY = 'store_holidays';

    /**
     * 正規化済みの日付文字列（Y-m-d）の配列を返す。
     *
     * @return list<string>
     */
    public static function dates(): array
    {
        $raw = Setting::getValue(self::KEY, []);

        return self::normalizeStored($raw);
    }

    /**
     * @return array<string, true> Y-m-d => true
     */
    public static function dateSet(): array
    {
        $out = [];
        foreach (self::dates() as $d) {
            $out[$d] = true;
        }

        return $out;
    }

    public static function isHoliday(string $dateYmd): bool
    {
        return isset(self::dateSet()[$dateYmd]);
    }

    /**
     * @param  mixed  $raw  DB の value（配列想定）
     * @return list<string>
     */
    public static function normalizeStored(mixed $raw): array
    {
        if ($raw === null || $raw === '') {
            return [];
        }

        if (is_string($raw)) {
            return self::parseAndValidate($raw);
        }

        if (! is_array($raw)) {
            return [];
        }

        $lines = [];
        foreach ($raw as $item) {
            if (is_string($item)) {
                $lines[] = $item;
            } elseif (is_numeric($item)) {
                $lines[] = (string) $item;
            }
        }

        return self::parseAndValidate(implode("\n", $lines));
    }

    /**
     * フォーム入力（カンマ・改行区切り）を検証し Y-m-d の昇順ユニーク配列にする。
     *
     * @return list<string>
     */
    public static function parseAndValidate(?string $raw): array
    {
        if ($raw === null || trim($raw) === '') {
            return [];
        }

        $parts = preg_split('/[\s,]+/u', str_replace(["\r\n", "\r"], "\n", $raw), -1, PREG_SPLIT_NO_EMPTY);
        if ($parts === false) {
            return [];
        }

        $seen = [];
        $errors = [];
        foreach ($parts as $i => $part) {
            $t = trim((string) $part);
            if ($t === '') {
                continue;
            }
            try {
                $c = Carbon::parse($t)->startOfDay();
            } catch (\Throwable) {
                $errors[] = '行 '.($i + 1).': 日付として解釈できません（'.$t.'）';

                continue;
            }
            $ymd = $c->toDateString();
            $seen[$ymd] = true;
        }

        if ($errors !== []) {
            throw ValidationException::withMessages([
                'value' => $errors,
            ]);
        }

        $out = array_keys($seen);
        sort($out);

        return array_values($out);
    }

    /**
     * DB 保存用: 1行1日付のプレーンテキスト（表示用）
     */
    public static function formatForTextarea(mixed $raw): string
    {
        $dates = self::normalizeStored($raw);

        return implode("\n", $dates);
    }
}
