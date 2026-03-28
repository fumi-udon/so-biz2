<?php

namespace App\Support;

use JsonException;

/**
 * 設定の「リスト系キー」をカンマ区切りテキスト ⇔ DB の JSON 配列に変換する。
 */
final class SettingFormValue
{
    /**
     * DB の値をフォーム用のカンマ区切り1行にする。
     */
    public static function arrayToCommaLine(mixed $state): string
    {
        if ($state === null || $state === '') {
            return '';
        }

        if (is_string($state)) {
            try {
                $decoded = json_decode($state, true, 512, JSON_THROW_ON_ERROR);
                $state = $decoded;
            } catch (JsonException) {
                return trim($state);
            }
        }

        if (! is_array($state)) {
            return '';
        }

        $parts = [];
        foreach ($state as $item) {
            if (is_string($item)) {
                $t = trim($item);
                if ($t !== '') {
                    $parts[] = $t;
                }
            } elseif (is_numeric($item)) {
                $parts[] = (string) $item;
            }
        }

        return implode(', ', $parts);
    }

    /**
     * カンマ区切り入力を DB 用の文字列配列にする。
     *
     * @return list<string>
     */
    public static function commaLineToArray(?string $line): array
    {
        if ($line === null || trim($line) === '') {
            return [];
        }

        $parts = preg_split('/\s*,\s*/', $line, -1, PREG_SPLIT_NO_EMPTY);
        if ($parts === false) {
            return [];
        }

        $out = [];
        foreach ($parts as $p) {
            $t = trim((string) $p);
            if ($t !== '') {
                $out[] = $t;
            }
        }

        return array_values($out);
    }
}
