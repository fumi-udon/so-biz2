<?php

namespace App\Actions\Pos\Takeaway;

use App\Domains\Pos\Tables\TableCategory;
use App\Models\RestaurantTable;
use App\Models\Shop;
use App\Services\Pos\TableSessionLifecycleService;
use Illuminate\Support\Facades\DB;

/**
 * Takeaway 専用: セッションに顧客情報を紐づけ、Guest メニュー URL を返す。
 */
final class StartTakeawayGuestFlowAction
{
    public function __construct(
        private readonly TableSessionLifecycleService $sessions = new TableSessionLifecycleService,
    ) {}

    /**
     * @return string Absolute Guest menu URL
     */
    public function execute(int $shopId, int $restaurantTableId, string $customerName, string $customerPhone): string
    {
        if (TableCategory::tryResolveFromId($restaurantTableId) !== TableCategory::Takeaway) {
            throw new \InvalidArgumentException('Not a takeaway table id.');
        }

        $name = trim($customerName);
        $phone = trim($customerPhone);
        if ($name === '' || $phone === '') {
            throw new \InvalidArgumentException('Customer name and phone are required.');
        }

        return DB::transaction(function () use ($shopId, $restaurantTableId, $name, $phone): string {
            /** @var RestaurantTable $table */
            $table = RestaurantTable::query()
                ->where('shop_id', $shopId)
                ->whereKey($restaurantTableId)
                ->lockForUpdate()
                ->firstOrFail();

            /** @var Shop $shop */
            $shop = Shop::query()->whereKey($shopId)->lockForUpdate()->firstOrFail();

            $slug = trim((string) ($shop->slug ?? ''));
            if ($slug === '') {
                throw new \RuntimeException('Shop slug is required for guest menu URL.');
            }

            $token = trim((string) ($table->qr_token ?? ''));
            if ($token === '') {
                throw new \RuntimeException('Restaurant table qr_token is missing.');
            }

            $session = $this->sessions->getOrCreateActiveSession($table);
            $session->customer_name = $name;
            $session->customer_phone = $phone;
            $session->save();

            return route('guest.menu', [
                'tenantSlug' => $slug,
                'tableToken' => $token,
            ], absolute: true);
        });
    }
}
