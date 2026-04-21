<?php

namespace Database\Seeders;

use App\Domains\Pos\Tables\TableCategory;
use App\Models\RestaurantTable;
use App\Models\Shop;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Phase 2 固定バケツ方式（docs/technical_contract_v4.md §空間主権）
 *   - Customer : id 10..29 (20 slots, labelled "TCxx")
 *   - Staff    : id 100..109 (10 slots, labelled "Sxxx")
 *   - Takeaway : id 200..219 (20 slots, labelled "TOxxx")
 *
 * IDs are pinned explicitly via DB::table()->insert() so that
 * {@see TableCategory::tryResolveFromId()} can resolve
 * the category solely from the primary key.
 *
 * Staff meal rows (id 100–104) are inserted by migration
 * `2026_04_21_000000_insert_staff_meal_tables.php` — not here — so deploy does not depend on this seeder.
 */
class RestaurantTableDashboardSeeder extends Seeder
{
    public function run(): void
    {
        $shop = Shop::query()
            ->where('is_active', true)
            ->orderBy('id')
            ->first();

        if ($shop === null) {
            $shop = Shop::query()->orderBy('id')->first();
        }
        if ($shop === null) {
            $shop = Shop::query()->create([
                'name' => 'Demo shop (POS test)',
                'slug' => 'demo-'.Str::lower(Str::ulid()),
                'is_active' => true,
            ]);
        }

        $now = now();

        $plan = [];
        for ($id = 10; $id <= 29; $id++) {
            $plan[$id] = 'TC'.str_pad((string) ($id - 9), 2, '0', STR_PAD_LEFT);
        }
        for ($id = 100; $id <= 109; $id++) {
            $plan[$id] = 'Staff '.str_pad((string) ($id - 99), 2, '0', STR_PAD_LEFT);
        }
        for ($id = 200; $id <= 219; $id++) {
            $plan[$id] = 'TO'.str_pad((string) ($id - 199), 2, '0', STR_PAD_LEFT);
        }

        foreach ($plan as $id => $name) {
            $exists = RestaurantTable::query()->whereKey($id)->exists();
            if ($exists) {
                continue;
            }
            DB::table('restaurant_tables')->insert([
                'id' => $id,
                'shop_id' => $shop->id,
                'name' => $name,
                'qr_token' => bin2hex(random_bytes(32)),
                'sort_order' => $id,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }
}
