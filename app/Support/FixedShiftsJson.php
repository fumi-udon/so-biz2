<?php

namespace App\Support;

use Illuminate\Support\Facades\Log;
use JsonException;

/**
 * Staff.fixed_shifts（週次シフト JSON）のフォーム表示・保存を一箇所で正規化する。
 */
final class FixedShiftsJson
{
    private const JSON_ENCODE_FLAGS = JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES;

    /**
     * JSON_THROW_ON_ERROR 由来の未捕捉例外を防ぎ、失敗時は空テンプレートの JSON を返す。
     */
    private static function encodePretty(mixed $value): string
    {
        try {
            return json_encode($value, self::JSON_ENCODE_FLAGS | JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            Log::warning('FixedShiftsJson: json_encode failed', ['message' => $e->getMessage()]);
            $fallback = json_encode(self::emptyWeekStructure(), self::JSON_ENCODE_FLAGS);

            return is_string($fallback) ? $fallback : '[]';
        }
    }

    /**
     * 7 日分・各日 lunch / dinner（null 可）の空テンプレート。
     *
     * @return array<string, array{lunch: null, dinner: null}>
     */
    public static function emptyWeekStructure(): array
    {
        $out = [];
        foreach (FixedShiftsCsv::DAYS as $day) {
            $out[$day] = [
                'lunch' => null,
                'dinner' => null,
            ];
        }

        return $out;
    }

    /**
     * DB またはフォームから来た値を、必ず 7 日キー揃いの配列にマージする。
     *
     * @param  array<string, mixed>|null  $fromDb
     * @return array<string, array{lunch: mixed, dinner: mixed}>
     */
    public static function mergeWithTemplate(?array $fromDb): array
    {
        $template = self::emptyWeekStructure();
        if ($fromDb === null || $fromDb === []) {
            return $template;
        }

        foreach (FixedShiftsCsv::DAYS as $day) {
            if (! isset($fromDb[$day]) || ! is_array($fromDb[$day])) {
                continue;
            }
            $dayVal = $fromDb[$day];
            $template[$day] = [
                'lunch' => array_key_exists('lunch', $dayVal) ? $dayVal['lunch'] : null,
                'dinner' => array_key_exists('dinner', $dayVal) ? $dayVal['dinner'] : null,
            ];
        }

        return $template;
    }

    /**
     * Textarea / エディタ用の JSON 文字列（整形）。
     */
    public static function toPrettyJsonString(mixed $state): string
    {
        if ($state === null || $state === '') {
            return self::encodePretty(self::emptyWeekStructure());
        }

        if (is_array($state)) {
            $merged = self::mergeWithTemplate($state);

            return self::encodePretty($merged);
        }

        if (is_string($state)) {
            $trim = trim($state);
            if ($trim === '') {
                return self::encodePretty(self::emptyWeekStructure());
            }

            try {
                $decoded = json_decode($trim, true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException) {
                return $state;
            }

            if (! is_array($decoded)) {
                return self::encodePretty(self::emptyWeekStructure());
            }

            return self::encodePretty(self::mergeWithTemplate($decoded));
        }

        return self::encodePretty(self::emptyWeekStructure());
    }

    /**
     * フォーム送信値を DB 用の配列に変換（検証ルールの前に呼ぶ）。
     *
     * @return array<string, mixed>|null
     */
    public static function toPersistedArray(mixed $state): ?array
    {
        if ($state === null) {
            return self::emptyWeekStructure();
        }

        if ($state === '') {
            return self::emptyWeekStructure();
        }

        if (is_array($state)) {
            return self::mergeWithTemplate($state);
        }

        if (! is_string($state)) {
            return self::emptyWeekStructure();
        }

        $trim = trim($state);
        if ($trim === '') {
            return self::emptyWeekStructure();
        }

        try {
            $decoded = json_decode($trim, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }

        if (! is_array($decoded)) {
            return null;
        }

        return self::mergeWithTemplate($decoded);
    }

    /**
     * 整形のみ（サフィックスアクション用）。無効な JSON のときは元文字列を返す。
     */
    public static function tryPrettyPrint(string $raw): string
    {
        $trim = trim($raw);
        if ($trim === '') {
            return self::toPrettyJsonString(null);
        }

        try {
            $decoded = json_decode($trim, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return $raw;
        }

        if (! is_array($decoded)) {
            return $raw;
        }

        return self::encodePretty(self::mergeWithTemplate($decoded));
    }
}
