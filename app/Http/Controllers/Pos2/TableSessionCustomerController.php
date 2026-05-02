<?php

namespace App\Http\Controllers\Pos2;

use App\Domains\Pos\Tables\TableCategory;
use App\Enums\TableSessionStatus;
use App\Http\Controllers\Controller;
use App\Models\RestaurantTable;
use App\Models\TableSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * POS V2: テイクアウト卓セッションの客名・電話（DB 権威）。
 */
final class TableSessionCustomerController extends Controller
{
    public function update(Request $request, int $session): JsonResponse
    {
        $shopId = $this->resolveShopId($request);
        if ($shopId < 1) {
            return response()->json(['message' => 'Shop not configured'], 400);
        }

        $validated = $request->validate([
            'name' => ['nullable', 'string', 'max:191'],
            'tel' => ['nullable', 'string', 'max:64'],
        ]);

        $sessionModel = TableSession::query()
            ->where('shop_id', $shopId)
            ->whereKey($session)
            ->first();

        if ($sessionModel === null) {
            return response()->json(['message' => 'Session not found'], 404);
        }

        if ($sessionModel->status !== TableSessionStatus::Active) {
            return response()->json(['message' => 'Session is not active'], 422);
        }

        $table = RestaurantTable::query()
            ->where('shop_id', $shopId)
            ->whereKey($sessionModel->restaurant_table_id)
            ->first();

        if ($table === null) {
            return response()->json(['message' => 'Table not found'], 404);
        }

        if (TableCategory::resolveFromModel($table) !== TableCategory::Takeaway) {
            return response()->json(['message' => 'Not a takeaway table session'], 422);
        }

        $nameRaw = $validated['name'] ?? null;
        $telRaw = $validated['tel'] ?? null;

        $name = is_string($nameRaw) ? trim($nameRaw) : '';
        $tel = is_string($telRaw) ? trim($telRaw) : '';

        $sessionModel->update([
            'customer_name' => $name !== '' ? $name : null,
            'customer_phone' => $tel !== '' ? $tel : null,
        ]);

        return response()->json(['ok' => true]);
    }

    private function resolveShopId(Request $request): int
    {
        $candidate = (int) ($request->session()->get('pos2.active_shop_id')
            ?? $request->session()->get('kds.active_shop_id')
            ?? env('POS_DEFAULT_SHOP_ID', 0));

        return max(0, $candidate);
    }
}
