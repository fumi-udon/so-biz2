<?php

use App\Support\Pos\StaffTableSettlementPricing;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Staff meal ghost tiles (restaurant_table id 100–104) are required infrastructure for
 * {@see StaffTableSettlementPricing} and the POS floor grid — not optional seed data.
 * Deploy: `php artisan migrate` ensures these rows exist when at least one shop is present.
 */
return new class extends Migration
{
    public function up(): void
    {
        $shop = DB::table('shops')
            ->where('is_active', true)
            ->orderBy('id')
            ->first();

        if ($shop === null) {
            $shop = DB::table('shops')->orderBy('id')->first();
        }

        if ($shop === null) {
            return;
        }

        $shopId = (int) $shop->id;
        $now = now();

        $mandatoryStaffRows = [];
        for ($id = 100; $id <= 104; $id++) {
            $mandatoryStaffRows[] = [
                'id' => $id,
                'shop_id' => $shopId,
                'name' => 'Staff '.str_pad((string) ($id - 99), 2, '0', STR_PAD_LEFT),
                'sort_order' => $id,
                'is_active' => true,
                'updated_at' => $now,
            ];
        }

        DB::table('restaurant_tables')->upsert(
            $mandatoryStaffRows,
            ['id'],
            ['shop_id', 'name', 'sort_order', 'is_active', 'updated_at']
        );
    }

    public function down(): void
    {
        DB::table('restaurant_tables')->whereBetween('id', [100, 104])->delete();
    }
};
