<?php

namespace App\Http\Controllers\Pos2;

use App\Actions\Pos2\PurgePos2DevFloorDataAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * POS V2 開発専用 API（`config('app.pos2_debug')` が true のときのみ有効）。
 */
final class Pos2DevController extends Controller
{
    public function purgeFloorData(Request $request, PurgePos2DevFloorDataAction $purge): JsonResponse
    {
        if (! config('app.pos2_debug')) {
            return response()->json(['message' => 'POS2 dev endpoints are disabled.'], 403);
        }

        $shopId = $this->resolveShopId($request);
        if ($shopId < 1) {
            return response()->json(['message' => 'Shop not configured'], 400);
        }

        $deletedSessions = $purge->execute($shopId);

        return response()->json([
            'message'                 => 'OK',
            'deleted_table_sessions'  => $deletedSessions,
            'shop_id'                 => $shopId,
        ]);
    }

    private function resolveShopId(Request $request): int
    {
        $candidate = (int) ($request->session()->get('pos2.active_shop_id')
            ?? $request->session()->get('kds.active_shop_id')
            ?? env('POS_DEFAULT_SHOP_ID', 0));

        return max(0, $candidate);
    }
}
