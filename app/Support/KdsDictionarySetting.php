<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Setting;

/**
 * KDS 表示名変換辞書（テキスト、店舗スコープの Setting キー）。
 */
final class KdsDictionarySetting
{
    public static function key(int $shopId): string
    {
        return 'kds_'.$shopId.'_dictionary_text';
    }

    /**
     * KDS V2 等で辞書 JSON をキャッシュする際のキー（店舗別）。
     */
    public static function jsonCacheKey(int $shopId): string
    {
        return 'kds2_dict_json_v2_'.$shopId;
    }

    /**
     * 辞書キーおよびチケット上のトッピング名の照合用に正規化する（大文字小文字無視・空白除去）。
     * 例: "Extra Spicy", "extra  spicy", "spiCy" → 同一キー。
     */
    public static function normalizeMatchKey(string $s): string
    {
        $s = trim($s);
        $collapsed = preg_replace('/\s+/u', '', $s);

        return mb_strtolower(is_string($collapsed) ? $collapsed : $s, 'UTF-8');
    }

    public static function getText(int $shopId): string
    {
        if ($shopId < 1) {
            return '';
        }

        $raw = Setting::getValue(self::key($shopId), '');

        return self::normalizeToString($raw);
    }

    public static function saveText(int $shopId, string $text): void
    {
        if ($shopId < 1) {
            return;
        }

        Setting::query()->updateOrCreate(
            ['key' => self::key($shopId)],
            [
                'value' => $text,
                'description' => 'KDS 表示名変換辞書テキスト（店舗 '.$shopId.'）',
            ]
        );
    }

    private static function normalizeToString(mixed $raw): string
    {
        if (is_string($raw)) {
            return $raw;
        }

        if ($raw === null) {
            return '';
        }

        if (is_scalar($raw)) {
            return (string) $raw;
        }

        return '';
    }
}
