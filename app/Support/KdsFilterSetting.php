<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Setting;

/**
 * KDS Kitchen/Hall 向けメニューカテゴリ ID（店舗スコープの Setting キー）。
 */
final class KdsFilterSetting
{
    public static function kitchenKey(int $shopId): string
    {
        return 'kds_'.$shopId.'_kitchen_categories';
    }

    public static function hallKey(int $shopId): string
    {
        return 'kds_'.$shopId.'_hall_categories';
    }

    /**
     * Kitchen または Hall のいずれかに 1 件以上のカテゴリが登録されている。
     */
    public static function isCategoryFilterConfigured(int $shopId): bool
    {
        if ($shopId < 1) {
            return false;
        }

        return self::kitchenCategoryIds($shopId) !== []
            || self::hallCategoryIds($shopId) !== [];
    }

    /**
     * @return list<int>
     */
    public static function kitchenCategoryIds(int $shopId): array
    {
        if ($shopId < 1) {
            return [];
        }

        return self::normalizeIdList(Setting::getValue(self::kitchenKey($shopId), []));
    }

    /**
     * @return list<int>
     */
    public static function hallCategoryIds(int $shopId): array
    {
        if ($shopId < 1) {
            return [];
        }

        return self::normalizeIdList(Setting::getValue(self::hallKey($shopId), []));
    }

    /**
     * @param  list<int|string>  $ids
     */
    public static function saveKitchenCategoryIds(int $shopId, array $ids): void
    {
        if ($shopId < 1) {
            return;
        }

        $normalized = self::normalizeIdList($ids);
        Setting::query()->updateOrCreate(
            ['key' => self::kitchenKey($shopId)],
            [
                'value' => $normalized,
                'description' => 'KDS Kitchen 向けメニューカテゴリ ID（店舗 '.$shopId.'）',
            ]
        );
    }

    /**
     * @param  list<int|string>  $ids
     */
    public static function saveHallCategoryIds(int $shopId, array $ids): void
    {
        if ($shopId < 1) {
            return;
        }

        $normalized = self::normalizeIdList($ids);
        Setting::query()->updateOrCreate(
            ['key' => self::hallKey($shopId)],
            [
                'value' => $normalized,
                'description' => 'KDS Hall 向けメニューカテゴリ ID（店舗 '.$shopId.'）',
            ]
        );
    }

    /**
     * @return list<int>
     */
    private static function normalizeIdList(mixed $raw): array
    {
        if (! is_array($raw)) {
            return [];
        }

        $out = [];
        foreach ($raw as $v) {
            $i = (int) $v;
            if ($i > 0) {
                $out[$i] = $i;
            }
        }

        return array_values($out);
    }
}
