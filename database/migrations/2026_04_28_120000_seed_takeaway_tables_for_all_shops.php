<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * 全店舗にテイクアウト卓 20 口を割り当てる。主キーは店舗ごとに
 * `(shop_id - 1) * 1000 + (200..219)` とし、グローバルな id 衝突を避ける。
 *
 * @see TableCategory::canonicalSlot()
 */
return new class extends Migration
{
    private const int TAKEAWAY_SLOT_MIN = 200;

    private const int TAKEAWAY_SLOT_MAX = 219;

    /** 1 店舗あたりの主キーブロック幅（canonical slot は % 1000）。 */
    private const int SHOP_ID_BLOCK = 1000;

    public function up(): void
    {
        if (! Schema::hasTable('shops') || ! Schema::hasTable('restaurant_tables')) {
            return;
        }

        $shops = DB::table('shops')->orderBy('id')->get(['id']);
        if ($shops->isEmpty()) {
            return;
        }

        $now = now();

        foreach ($shops as $shop) {
            $shopId = (int) $shop->id;
            $base = ($shopId - 1) * self::SHOP_ID_BLOCK;

            for ($slot = self::TAKEAWAY_SLOT_MIN; $slot <= self::TAKEAWAY_SLOT_MAX; $slot++) {
                $id = $base + $slot;
                $ordinal = $slot - self::TAKEAWAY_SLOT_MIN + 1;
                $name = 'TO'.str_pad((string) $ordinal, 2, '0', STR_PAD_LEFT);

                $existing = DB::table('restaurant_tables')->where('id', $id)->first();

                $qrToken = $existing && ! empty($existing->qr_token)
                    ? (string) $existing->qr_token
                    : Str::random(32);

                $payload = [
                    'shop_id' => $shopId,
                    'name' => $name,
                    'qr_token' => $qrToken,
                    'sort_order' => $slot,
                    'is_active' => true,
                    'updated_at' => $now,
                ];

                if ($existing === null) {
                    $payload['created_at'] = $now;
                }

                DB::table('restaurant_tables')->updateOrInsert(
                    ['id' => $id],
                    $payload
                );
            }
        }
    }

    /**
     * 危険: セッション等が参照している場合は外部キーで失敗する。
     */
    public function down(): void
    {
        if (! Schema::hasTable('shops') || ! Schema::hasTable('restaurant_tables')) {
            return;
        }

        $shopIds = DB::table('shops')->orderBy('id')->pluck('id');
        foreach ($shopIds as $shopId) {
            $shopId = (int) $shopId;
            $base = ($shopId - 1) * self::SHOP_ID_BLOCK;
            DB::table('restaurant_tables')->whereBetween('id', [
                $base + self::TAKEAWAY_SLOT_MIN,
                $base + self::TAKEAWAY_SLOT_MAX,
            ])->delete();
        }
    }
};
