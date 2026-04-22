<?php

namespace Tests\Support;

use App\Domains\Pos\Tables\TableCategory;
use App\Enums\OrderLineStatus;
use App\Enums\OrderStatus;
use App\Enums\TableSessionStatus;
use App\Models\JobLevel;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\OrderLine;
use App\Models\PosOrder;
use App\Models\RestaurantTable;
use App\Models\Shop;
use App\Models\Staff;
use App\Models\TableSession;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Phase 2 ダッシュボードに関するテスト fixture 群。
 *
 * - 固定バケツ（canonical slot: Customer 10-39 / Staff 100-109 / Takeaway 200-219；多店舗は主キーに店舗ブロックを足す）を明示的に
 *   埋め込むため restaurant_tables は `DB::table()->insert()` で primary key を pin する。
 *   その他の model は Eloquent で生成することで冗長な insert() 乱用を避ける。
 * - 時系列の判定テストが DB の秒精度で壊れないよう、
 *   `Carbon::setTestNow()` で freeze した時刻を基準に created_at を Eloquent に任せて刻む。
 *
 * RefreshDatabase を併用する TestCase 側で `use BuildsPosDashboardFixtures;` する想定。
 */
trait BuildsPosDashboardFixtures
{
    protected function makeShop(string $slugSuffix = ''): Shop
    {
        $slug = 'pos-fx-'.($slugSuffix !== '' ? $slugSuffix.'-' : '').Str::lower(Str::random(8));

        return Shop::query()->create([
            'name' => 'POS fixture shop',
            'slug' => $slug,
            'is_active' => true,
        ]);
    }

    protected function makeCustomerTable(Shop $shop, int $id = 10, ?string $name = null): RestaurantTable
    {
        $this->assertRange($id, 10, 29, 'Customer');

        return $this->pinTable($shop, $id, $name ?? ('TC'.str_pad((string) ($id - 9), 2, '0', STR_PAD_LEFT)));
    }

    protected function makeStaffTable(Shop $shop, int $id = 100, ?string $name = null): RestaurantTable
    {
        $this->assertRange($id, 100, 109, 'Staff');

        return $this->pinTable($shop, $id, $name ?? ('Staff '.str_pad((string) ($id - 99), 2, '0', STR_PAD_LEFT)));
    }

    protected function makeTakeawayTable(Shop $shop, int $id = 200, ?string $name = null): RestaurantTable
    {
        $slot = TableCategory::canonicalSlot($id);
        $this->assertRange($slot, 200, 219, 'Takeaway');

        return $this->pinTable($shop, $id, $name ?? ('TO'.str_pad((string) ($slot - 199), 2, '0', STR_PAD_LEFT)));
    }

    protected function openActiveSession(Shop $shop, RestaurantTable $table): TableSession
    {
        return TableSession::query()->create([
            'shop_id' => $shop->id,
            'restaurant_table_id' => $table->id,
            'token' => 'tok-'.Str::lower(Str::random(24)),
            'status' => TableSessionStatus::Active,
            'opened_at' => Carbon::now(),
        ]);
    }

    /**
     * セッションの `last_addition_printed_at` を指定時刻で更新する。
     */
    protected function markAdditionPrintedAt(TableSession $session, Carbon $at): TableSession
    {
        $session->last_addition_printed_at = $at->toDateTimeString();
        $session->save();

        return $session->fresh();
    }

    /**
     * 指定時刻を「現在時刻」としたうえで PosOrder を 1 件作成する（created_at も同値になる）。
     */
    protected function placeOrderAt(
        Shop $shop,
        TableSession $session,
        OrderStatus $status,
        Carbon $at,
        int $totalPriceMinor = 0
    ): PosOrder {
        Carbon::setTestNow($at);
        try {
            return PosOrder::query()->create([
                'shop_id' => $shop->id,
                'table_session_id' => $session->id,
                'status' => $status,
                'total_price_minor' => $totalPriceMinor,
                'rounding_adjustment_minor' => 0,
                'placed_at' => $status === OrderStatus::Placed ? $at : null,
            ]);
        } finally {
            // 副作用を残さないよう明示解除（後続の assert で now() を使ってもズレない）。
            Carbon::setTestNow();
        }
    }

    /**
     * 既存 PosOrder に OrderLine を追加する（created_at を指定時刻で刻む）。
     */
    protected function addLineAt(Shop $shop, PosOrder $order, Carbon $at): OrderLine
    {
        $menuItem = $this->ensureMenuItem($shop);

        Carbon::setTestNow($at);
        try {
            return OrderLine::query()->create([
                'shop_id' => $shop->id,
                'order_id' => $order->id,
                'menu_item_id' => $menuItem->id,
                'qty' => 1,
                'unit_price_minor' => 0,
                'line_total_minor' => 0,
                'snapshot_name' => (string) $menuItem->name,
                'snapshot_kitchen_name' => (string) $menuItem->name,
                'snapshot_options_payload' => ['toppings' => [], 'note' => '', 'style' => null],
                'status' => OrderLineStatus::Placed,
            ]);
        } finally {
            Carbon::setTestNow();
        }
    }

    /**
     * Phase 3: 価格を持った PosOrder + OrderLine 1本をひとかたまりで作成する。
     * Settlement テストで多彩な金額/割引パターンを短く書くための helper。
     *
     * @return array{order: PosOrder, line: OrderLine}
     */
    protected function placeLinedOrder(
        Shop $shop,
        TableSession $session,
        int $lineTotalMinor,
        OrderStatus $status = OrderStatus::Confirmed,
        int $qty = 1,
        int $lineDiscountMinor = 0,
        int $orderDiscountMinor = 0,
    ): array {
        $menuItem = $this->ensureMenuItem($shop);
        $unit = $qty > 0 ? intdiv($lineTotalMinor, $qty) : 0;

        $order = PosOrder::query()->create([
            'shop_id' => $shop->id,
            'table_session_id' => $session->id,
            'status' => $status,
            'total_price_minor' => $lineTotalMinor,
            'order_discount_minor' => $orderDiscountMinor,
            'rounding_adjustment_minor' => 0,
            'placed_at' => Carbon::now(),
        ]);

        $line = OrderLine::query()->create([
            'shop_id' => $shop->id,
            'order_id' => $order->id,
            'menu_item_id' => $menuItem->id,
            'qty' => $qty,
            'unit_price_minor' => $unit,
            'line_total_minor' => $lineTotalMinor,
            'line_discount_minor' => $lineDiscountMinor,
            'snapshot_name' => (string) $menuItem->name,
            'snapshot_kitchen_name' => (string) $menuItem->name,
            'snapshot_options_payload' => ['toppings' => [], 'note' => '', 'style' => null],
            'status' => OrderLineStatus::Placed,
        ]);

        return ['order' => $order, 'line' => $line];
    }

    /**
     * Phase 3: 承認者 Staff（PIN + Job Level 付き）を生成する helper。
     * Record*DiscountAction / Cloture PIN テストの共通データ。
     */
    protected function makeApprover(Shop $shop, int $level = 3, string $pin = '1234', ?string $name = null): Staff
    {
        $jobLevel = JobLevel::query()->firstOrCreate(
            ['level' => $level],
            ['name' => 'Lv'.$level, 'default_weight' => 1],
        );

        return Staff::query()->create([
            'shop_id' => $shop->id,
            'name' => $name ?? ('Approver Lv'.$level),
            'pin_code' => $pin,
            'role' => 'staff',
            'is_manager' => $level >= 4,
            'is_active' => true,
            'job_level_id' => $jobLevel->id,
        ]);
    }

    /**
     * Phase 3: Filament 操作者 User を生成する helper。
     */
    protected function makeOperator(string $slug = ''): User
    {
        $email = 'op-'.Str::lower(Str::random(8)).($slug !== '' ? '-'.$slug : '').'@example.test';

        return User::query()->create([
            'name' => 'Operator '.Str::random(4),
            'email' => $email,
            'password' => bcrypt('password'),
        ]);
    }

    private function ensureMenuItem(Shop $shop): MenuItem
    {
        $category = MenuCategory::query()->firstOrCreate(
            ['shop_id' => $shop->id, 'name' => 'Fx Cat'],
            [
                'slug' => 'fx-cat-'.Str::lower(Str::random(5)),
                'sort_order' => 1,
                'is_active' => true,
            ]
        );

        return MenuItem::query()->firstOrCreate(
            ['shop_id' => $shop->id, 'name' => 'Fx Item'],
            [
                'menu_category_id' => $category->id,
                'slug' => 'fx-item-'.Str::lower(Str::random(5)),
                'kitchen_name' => 'Fx Item',
                'from_price_minor' => 0,
                'sort_order' => 1,
                'is_active' => true,
            ]
        );
    }

    private function pinTable(Shop $shop, int $id, string $name): RestaurantTable
    {
        DB::table('restaurant_tables')->insert([
            'id' => $id,
            'shop_id' => $shop->id,
            'name' => $name,
            'qr_token' => 'qr-'.bin2hex(random_bytes(8)),
            'sort_order' => $id,
            'is_active' => true,
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);

        return RestaurantTable::query()->whereKey($id)->firstOrFail();
    }

    private function assertRange(int $id, int $min, int $max, string $label): void
    {
        if ($id < $min || $id > $max) {
            throw new \InvalidArgumentException(
                "{$label} table id must be within {$min}..{$max}, got {$id}"
            );
        }
    }
}
