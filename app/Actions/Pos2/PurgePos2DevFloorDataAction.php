<?php

namespace App\Actions\Pos2;

use App\Models\TableSession;
use App\Services\Pos\TableDashboardQueryService;
use Illuminate\Support\Facades\DB;

/**
 * 開発用: 指定ショップの卓セッションをすべて削除し、紐づく PosOrder / OrderLine 等を CASCADE で除去する。
 * 本番では {@see config('app.pos2_debug')} ゲートの外から呼ばないこと。
 */
final class PurgePos2DevFloorDataAction
{
    public function execute(int $shopId): int
    {
        if ($shopId < 1) {
            return 0;
        }

        $deleted = 0;

        DB::transaction(function () use ($shopId, &$deleted): void {
            DB::table('pos_line_deletion_audit_logs')->where('shop_id', $shopId)->delete();

            $deleted = (int) TableSession::query()->where('shop_id', $shopId)->count();
            TableSession::query()->where('shop_id', $shopId)->delete();
        });

        app(TableDashboardQueryService::class)->forgetCachedDashboard($shopId);

        return $deleted;
    }
}
