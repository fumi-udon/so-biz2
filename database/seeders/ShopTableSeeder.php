<?php

namespace Database\Seeders;

use App\Models\RestaurantTable;
use App\Models\Shop;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * アクティブ店舗のうち、まだ restaurant_tables が0件の店舗だけに
 * 既定の卓・スタッフ食・テイクアウト枠を冪等に作成する。
 *
 * 既に1件でもある店舗はスキップ（運用中データの保護）。
 */
class ShopTableSeeder extends Seeder
{
    public function run(): void
    {
        $shops = Shop::query()
            ->where('is_active', true)
            ->orderBy('id')
            ->get();

        foreach ($shops as $shop) {
            $exists = RestaurantTable::query()
                ->where('shop_id', $shop->id)
                ->exists();

            if ($exists) {
                if ($this->command !== null) {
                    $this->command->info("Shop {$shop->id} ({$shop->name}): 既存テーブルあり — スキップ");
                }

                continue;
            }

            $prefix = self::resolveClientQrPrefix($shop);

            DB::transaction(function () use ($shop, $prefix): void {
                self::insertDefaultTablesForShop((int) $shop->id, $prefix);
            });

            if ($this->command !== null) {
                $this->command->info("Shop {$shop->id} ({$shop->name}): デフォルトテーブルを生成しました (prefix: {$prefix})");
            }
        }
    }

    /**
     * Client 用 qr_token のプレフィックス（例: slug 由来 or shop{id}）。
     */
    public static function resolveClientQrPrefix(Shop $shop): string
    {
        $slug = $shop->getAttribute('slug');
        if (is_string($slug) && $slug !== '') {
            $ascii = Str::ascii($slug);
            $clean = preg_replace('/[^a-z0-9]+/i', '', $ascii);
            $clean = is_string($clean) ? strtolower($clean) : '';
            if ($clean !== '') {
                return Str::substr($clean, 0, 12);
            }
        }

        return 'shop'.$shop->id;
    }

    /**
     * 指定店舗に既定の restaurant_tables を挿入する（呼び出し側で空であることを保証すること）。
     *
     * - Client: T01〜T99（qr_token: {prefix}_01 … {prefix}_99）
     * - Staff Meal: ST01〜ST10（qr_token: ST01 …）
     * - Takeout: TK01〜TK20
     */
    public static function insertDefaultTablesForShop(int $shopId, string $clientQrPrefix): void
    {
        for ($i = 1; $i <= 99; $i++) {
            RestaurantTable::create([
                'shop_id' => $shopId,
                'name' => sprintf('T%02d', $i),
                'qr_token' => sprintf('%s_%02d', $clientQrPrefix, $i),
                'sort_order' => $i,
                'is_active' => true,
            ]);
        }

        for ($i = 1; $i <= 10; $i++) {
            RestaurantTable::create([
                'shop_id' => $shopId,
                'name' => sprintf('ST%02d', $i),
                'qr_token' => sprintf('ST%02d', $i),
                'sort_order' => 100 + $i,
                'is_active' => true,
            ]);
        }

        for ($i = 1; $i <= 20; $i++) {
            RestaurantTable::create([
                'shop_id' => $shopId,
                'name' => sprintf('TK%02d', $i),
                'qr_token' => sprintf('TK%02d', $i),
                'sort_order' => 200 + $i,
                'is_active' => true,
            ]);
        }
    }
}
