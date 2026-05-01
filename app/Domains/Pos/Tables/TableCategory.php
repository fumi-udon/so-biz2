<?php

namespace App\Domains\Pos\Tables;

use App\Models\RestaurantTable;
use InvalidArgumentException;

enum TableCategory: string
{
    case Customer = 'customer';
    case Staff = 'staff';
    case Takeaway = 'takeaway';

    /**
     * 複数店舗で `restaurant_tables.id` が衝突しないよう、店舗ごとに
     * `(shop_id - 1) * 1000` を足した主キーを割り当てる。レガシー（単一店・低 id）はそのまま解釈する。
     *
     * シード例: `database/migrations/*_seed_takeaway_tables_for_all_shops.php`
     */
    public static function canonicalSlot(int $restaurantTableId): int
    {
        if ($restaurantTableId < 1000) {
            return $restaurantTableId;
        }

        return $restaurantTableId % 1000;
    }

    public static function tryResolveFromId(int $id): ?self
    {
        // Phase 3: categoryカラムから解決（正式実装）
        $cat = RestaurantTable::query()
            ->whereKey($id)
            ->value('category');

        if (is_string($cat) && $cat !== '') {
            return self::tryFrom($cat);
        }

        // フォールバック: categoryがnull・テストデータ等
        $s = self::canonicalSlot($id);

        return match (true) {
            $s >= 1 && $s <= 99 => self::Customer,
            $s >= 100 && $s <= 109 => self::Staff,
            $s >= 200 && $s <= 220 => self::Takeaway,
            default => null,
        };
    }

    /**
     * RestaurantTable モデルから category カラムを優先して解決する。
     * カラムが null または未ロードの場合は tryResolveFromId() にフォールバック。
     * Phase 3 完了後に tryResolveFromId() は廃止予定。
     */
    public static function resolveFromModel(RestaurantTable $table): ?self
    {
        $cat = $table->category ?? null;
        if (is_string($cat) && $cat !== '') {
            return self::tryFrom($cat);
        }

        return self::tryResolveFromId((int) $table->id);
    }

    public static function resolveFromIdOrFail(int $id): self
    {
        $resolved = self::tryResolveFromId($id);
        if ($resolved !== null) {
            return $resolved;
        }

        throw new InvalidArgumentException('Unknown table category id: '.$id);
    }
}
