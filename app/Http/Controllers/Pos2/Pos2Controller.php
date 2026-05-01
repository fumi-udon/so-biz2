<?php

namespace App\Http\Controllers\Pos2;

use App\Http\Controllers\Controller;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\RestaurantTable;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

final class Pos2Controller extends Controller
{
    private const MASTER_SCHEMA_VERSION = 1;

    public function index(Request $request): Response
    {
        Inertia::setRootView('pos2.app');

        return Inertia::render('Index', [
            'shop_id' => $this->resolveShopId($request),
        ]);
    }

    public function bootstrap(Request $request): JsonResponse
    {
        $shopId = $this->resolveShopId($request);

        $categories = MenuCategory::query()
            ->where('shop_id', $shopId)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get(['id', 'shop_id', 'name', 'slug', 'sort_order'])
            ->toArray();

        $menuItems = MenuItem::query()
            ->where('shop_id', $shopId)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get([
                'id',
                'shop_id',
                'menu_category_id',
                'name',
                'kitchen_name',
                'slug',
                'from_price_minor',
                'sort_order',
                'hero_image_path',
                'options_payload',
            ])
            ->map(function (MenuItem $item): array {
                $rawPayload = is_array($item->options_payload) ? $item->options_payload : [];
                $rules = is_array($rawPayload['rules'] ?? null) ? $rawPayload['rules'] : [];
                $styles = is_array($rawPayload['styles'] ?? null) ? $rawPayload['styles'] : [];
                $toppings = is_array($rawPayload['toppings'] ?? null) ? $rawPayload['toppings'] : [];

                return [
                    'id' => (int) $item->id,
                    'shop_id' => (int) $item->shop_id,
                    'menu_category_id' => (int) $item->menu_category_id,
                    'name' => (string) $item->name,
                    'kitchen_name' => (string) $item->kitchen_name,
                    'slug' => (string) $item->slug,
                    'from_price_minor' => (int) $item->from_price_minor,
                    'sort_order' => (int) $item->sort_order,
                    'hero_image_path' => (string) $item->hero_image_path,
                    'options_payload' => [
                        'rules' => [
                            'style_required' => (bool) ($rules['style_required'] ?? false),
                        ],
                        'styles' => array_values(array_map(static function ($row): array {
                            $entry = is_array($row) ? $row : [];

                            return [
                                'id' => (string) ($entry['id'] ?? ''),
                                'name' => (string) ($entry['name'] ?? ''),
                                'price_minor' => max(0, (int) ($entry['price_minor'] ?? 0)),
                            ];
                        }, $styles)),
                        'toppings' => array_values(array_map(static function ($row): array {
                            $entry = is_array($row) ? $row : [];

                            return [
                                'id' => (string) ($entry['id'] ?? ''),
                                'name' => (string) ($entry['name'] ?? ''),
                                'price_delta_minor' => max(0, (int) ($entry['price_delta_minor'] ?? 0)),
                            ];
                        }, $toppings)),
                    ],
                ];
            })
            ->toArray();

        $tables = RestaurantTable::query()
            ->where('shop_id', $shopId)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get(['id', 'shop_id', 'name', 'sort_order'])
            ->toArray();

        $generatedAt = now()->toIso8601String();

        $clientTableLimit = $this->resolvePositiveTableDisplayLimit('pos_client_table_display');
        $staffTableLimit = $this->resolvePositiveTableDisplayLimit('pos_staff_table_display');
        $takeoutTableLimit = $this->resolvePositiveTableDisplayLimit('pos_takeout_table_display');

        Log::channel('pos2')->info('bootstrap.served', [
            'shop_id' => $shopId,
            'schema_version' => self::MASTER_SCHEMA_VERSION,
            'categories' => count($categories),
            'menu_items' => count($menuItems),
            'tables' => count($tables),
            'generated_at' => $generatedAt,
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return response()->json([
            'shop_id' => $shopId,
            'schema_version' => self::MASTER_SCHEMA_VERSION,
            'categories' => $categories,
            'menuItems' => $menuItems,
            'tables' => $tables,
            'generated_at' => $generatedAt,
            'client_table_limit' => $clientTableLimit,
            'staff_table_limit' => $staffTableLimit,
            'takeout_table_limit' => $takeoutTableLimit,
        ]);
    }

    /**
     * POS V2 注文送信スタブ（Phase 5 で本処理に接続するまでの仮設）。
     * 受信 JSON は未検証。201 のみ成功としてフロントがドラフトを破棄する。
     */
    public function submitOrderStub(Request $request): JsonResponse
    {
        if (config('app.pos2_debug')) {
            try {
                $payload = $request->all();
                $lines = \is_array($payload['lines'] ?? null) ? $payload['lines'] : [];
                $clientSubmitId = $payload['client_submit_id'] ?? null;
                $shopId = $payload['shop_id'] ?? null;
                $tableSessionId = $payload['table_session_id'] ?? null;

                Log::channel('pos2')->info('order.submit.received', [
                    'client_submit_id' => $clientSubmitId,
                    'line_count' => \count($lines),
                    'shop_id' => $shopId,
                    'table_session_id' => $tableSessionId,
                    'ip' => $request->ip(),
                ]);
            } catch (\Throwable) {
                // 調査ログは本処理を止めない
            }
        }

        return response()->json([
            'message' => 'Stub OK',
            'order_id' => 999,
        ], 201);
    }

    private function resolveShopId(Request $request): int
    {
        $candidate = (int) ($request->session()->get('pos2.active_shop_id')
            ?? $request->session()->get('kds.active_shop_id')
            ?? env('POS_DEFAULT_SHOP_ID', 0));

        return max(0, $candidate);
    }

    /**
     * Filament 設定値を正の整数に正規化。型不正・0 以下は 100。
     */
    private function resolvePositiveTableDisplayLimit(string $key): int
    {
        $raw = Setting::getValue($key, null);

        if ($raw === null) {
            return 100;
        }

        if (\is_array($raw) || \is_object($raw)) {
            return 100;
        }

        if (\is_bool($raw)) {
            return 100;
        }

        if (\is_int($raw)) {
            return $raw > 0 ? $raw : 100;
        }

        if (\is_float($raw)) {
            if ($raw <= 0.0 || $raw > (float) \PHP_INT_MAX) {
                return 100;
            }

            $asInt = (int) $raw;

            return ((float) $asInt) === $raw && $asInt > 0 ? $asInt : 100;
        }

        if (\is_string($raw)) {
            $trimmed = trim($raw);
            if ($trimmed === '' || ! \is_numeric($trimmed)) {
                return 100;
            }

            $asFloat = (float) $trimmed;
            $asInt = (int) $asFloat;

            if ($asInt <= 0 || $asFloat !== (float) $asInt) {
                return 100;
            }

            return $asInt;
        }

        return 100;
    }
}
