<?php

namespace App\Domains\Pos\Tables;

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
        $s = self::canonicalSlot($id);

        return match (true) {
            $s >= 10 && $s <= 39 => self::Customer,
            $s >= 100 && $s <= 109 => self::Staff,
            $s >= 200 && $s <= 219 => self::Takeaway,
            default => null,
        };
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
