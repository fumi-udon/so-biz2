<?php

namespace App\Support;

use App\Models\Setting;

class InventorySettingOptions
{
    public const KEY_TIMING = 'inventory_timing_options';

    public const KEY_CATEGORY = 'inventory_category_options';

    public const KEY_UNIT = 'inventory_unit_options';

    /**
     * 棚卸しマスタ用の設定キーか（タグ入力 UI を使う）
     */
    public static function isListKey(?string $key): bool
    {
        return in_array($key, [
            self::KEY_TIMING,
            self::KEY_CATEGORY,
            self::KEY_UNIT,
        ], true);
    }

    /**
     * @return array<string, string> value => label（同一文字列）
     */
    public static function timingForSelect(?string $current = null): array
    {
        $opts = self::toKeyedOptions(Setting::getValue(self::KEY_TIMING, ['close']));

        return self::mergeCurrentIfMissing($opts, $current);
    }

    /**
     * @return array<string, string>
     */
    public static function categoryForSelect(?string $current = null): array
    {
        $opts = self::toKeyedOptions(Setting::getValue(self::KEY_CATEGORY, ['その他']));

        return self::mergeCurrentIfMissing($opts, $current);
    }

    /**
     * @return array<string, string>
     */
    public static function unitForSelect(?string $current = null): array
    {
        $opts = self::toKeyedOptions(Setting::getValue(self::KEY_UNIT, ['個']));

        return self::mergeCurrentIfMissing($opts, $current);
    }

    /**
     * @param  array<string, string>  $opts
     * @return array<string, string>
     */
    protected static function mergeCurrentIfMissing(array $opts, ?string $current): array
    {
        if (is_string($current) && $current !== '' && ! isset($opts[$current])) {
            $opts[$current] = $current;
        }

        return $opts;
    }

    /**
     * @return array<string, string>
     */
    protected static function toKeyedOptions(mixed $raw): array
    {
        if (! is_array($raw)) {
            return [];
        }

        $out = [];
        foreach ($raw as $item) {
            if (! is_string($item)) {
                continue;
            }
            $item = trim($item);
            if ($item === '') {
                continue;
            }
            $out[$item] = $item;
        }

        return $out;
    }
}
