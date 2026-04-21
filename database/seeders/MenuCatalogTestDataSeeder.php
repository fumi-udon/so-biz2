<?php

namespace Database\Seeders;

use App\Enums\MenuItemRoleCategory;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Shop;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 全店舗の menu_categories / menu_items / menu_item_dietary_badge を物理削除し、
 * 先頭の有効店舗（なければ slug=soya）に tapas / ramen / drink と約20品を投入する。
 *
 * 実行: ./vendor/bin/sail artisan db:seed --class=MenuCatalogTestDataSeeder
 */
class MenuCatalogTestDataSeeder extends Seeder
{
    public function run(): void
    {
        $shop = Shop::query()
            ->where('is_active', true)
            ->orderBy('id')
            ->first()
            ?? Shop::query()->where('slug', 'soya')->first()
            ?? Shop::query()->orderBy('id')->first();

        if ($shop === null) {
            $this->command?->error('No shop found. Create a shop first.');

            return;
        }

        $this->command?->info('Wiping all menu categories & items, then seeding for shop: '.$shop->name.' (id '.$shop->id.').');

        DB::transaction(function () use ($shop): void {
            $this->purgeAllMenuCatalog();

            $tapas = MenuCategory::query()->create([
                'shop_id' => $shop->id,
                'name' => 'Tapas',
                'slug' => 'tapas',
                'sort_order' => 0,
                'is_active' => true,
            ]);
            $ramen = MenuCategory::query()->create([
                'shop_id' => $shop->id,
                'name' => 'Ramen',
                'slug' => 'ramen',
                'sort_order' => 1,
                'is_active' => true,
            ]);
            $drink = MenuCategory::query()->create([
                'shop_id' => $shop->id,
                'name' => 'Drink',
                'slug' => 'drink',
                'sort_order' => 2,
                'is_active' => true,
            ]);

            $rows = array_merge(
                $this->tapasRows($tapas->id, $shop->id),
                $this->ramenRows($ramen->id, $shop->id),
                $this->drinkRows($drink->id, $shop->id),
            );

            foreach ($rows as $i => $row) {
                MenuItem::query()->create(array_merge($row, [
                    'sort_order' => $i,
                ]));
            }
        });

        $this->command?->info('Done. Categories: tapas, ramen, drink. Items: '.MenuItem::query()->where('shop_id', $shop->id)->count().'.');
    }

    private function purgeAllMenuCatalog(): void
    {
        if (Schema::hasTable('menu_item_dietary_badge')) {
            DB::table('menu_item_dietary_badge')->delete();
        }
        if (Schema::hasTable('menu_items')) {
            // Physical delete (includes soft-deleted rows in the same table)
            DB::table('menu_items')->delete();
        }
        if (Schema::hasTable('menu_categories')) {
            DB::table('menu_categories')->delete();
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function tapasRows(int $categoryId, int $shopId): array
    {
        $base = static fn (array $x) => array_merge(
            [
                'shop_id' => $shopId,
                'menu_category_id' => $categoryId,
                'role_category' => MenuItemRoleCategory::Kitchen,
                'is_active' => true,
                'description' => null,
                'allergy_note' => null,
                'options_payload' => null,
            ],
            $x
        );

        $list = [
            ['name' => 'Edamame (salt)', 'from_price_minor' => 5000, 'kitchen_name' => 'Edamame (salt)'],
            ['name' => 'Patatas bravas', 'from_price_minor' => 8000, 'kitchen_name' => 'Patatas bravas'],
            ['name' => 'Croquetas jamón', 'from_price_minor' => 9000, 'kitchen_name' => 'Croquetas jamón'],
            ['name' => 'Calamares fritos', 'from_price_minor' => 12000, 'kitchen_name' => 'Calamares fritos'],
            ['name' => 'Albóndigas', 'from_price_minor' => 10000, 'kitchen_name' => 'Albóndigas'],
            ['name' => 'Gambas al ajillo', 'from_price_minor' => 15000, 'kitchen_name' => 'Gambas al ajillo'],
            ['name' => 'Pan con tomate', 'from_price_minor' => 4000, 'kitchen_name' => 'Pan con tomate'],
        ];

        return array_map(fn (array $r): array => $base($r), $list);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function ramenRows(int $categoryId, int $shopId): array
    {
        $base = static fn (array $x) => array_merge(
            [
                'shop_id' => $shopId,
                'menu_category_id' => $categoryId,
                'role_category' => MenuItemRoleCategory::Kitchen,
                'is_active' => true,
                'description' => null,
                'allergy_note' => null,
                'options_payload' => null,
            ],
            $x
        );

        $list = [
            ['name' => 'Shoyu Ramen', 'from_price_minor' => 16000, 'kitchen_name' => 'Shoyu Ramen'],
            ['name' => 'Miso Ramen', 'from_price_minor' => 16000, 'kitchen_name' => 'Miso Ramen'],
            ['name' => 'Tonkotsu Ramen', 'from_price_minor' => 18000, 'kitchen_name' => 'Tonkotsu Ramen'],
            ['name' => 'Tantanmen', 'from_price_minor' => 17000, 'kitchen_name' => 'Tantanmen'],
            ['name' => 'Shio Ramen', 'from_price_minor' => 15000, 'kitchen_name' => 'Shio Ramen'],
            ['name' => 'Spicy Miso Ramen', 'from_price_minor' => 17000, 'kitchen_name' => 'Spicy Miso Ramen'],
            ['name' => 'Tsukemen (dipping)', 'from_price_minor' => 19000, 'kitchen_name' => 'Tsukemen'],
        ];

        return array_map(fn (array $r): array => $base($r), $list);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function drinkRows(int $categoryId, int $shopId): array
    {
        $base = static fn (array $x) => array_merge(
            [
                'shop_id' => $shopId,
                'menu_category_id' => $categoryId,
                'role_category' => MenuItemRoleCategory::Drink,
                'is_active' => true,
                'description' => null,
                'allergy_note' => null,
                'options_payload' => null,
            ],
            $x
        );

        $list = [
            ['name' => 'Genmaicha (hot)', 'from_price_minor' => 5000, 'kitchen_name' => 'Genmaicha (hot)'],
            ['name' => 'Hojicha (hot)', 'from_price_minor' => 5000, 'kitchen_name' => 'Hojicha (hot)'],
            ['name' => 'Asahi (bottle)', 'from_price_minor' => 8000, 'kitchen_name' => 'Asahi'],
            ['name' => 'Calpis soda', 'from_price_minor' => 6000, 'kitchen_name' => 'Calpis soda'],
            ['name' => 'Coke (can)', 'from_price_minor' => 5000, 'kitchen_name' => 'Coke (can)'],
            ['name' => 'Mineral water', 'from_price_minor' => 4000, 'kitchen_name' => 'Mineral water'],
        ];

        return array_map(fn (array $r): array => $base($r), $list);
    }
}
