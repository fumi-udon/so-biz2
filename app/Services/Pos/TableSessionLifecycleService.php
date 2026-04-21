<?php

namespace App\Services\Pos;

use App\Enums\TableSessionStatus;
use App\Exceptions\GuestOrderValidationException;
use App\Models\RestaurantTable;
use App\Models\TableSession;
use Illuminate\Support\Str;

/**
 * ゲスト/スタッフ双方で、卓の有効 {@see TableSession} を一貫して取得/作成。
 */
final class TableSessionLifecycleService
{
    public function getOrCreateActiveSession(RestaurantTable $table): TableSession
    {
        $existing = TableSession::query()
            ->where('restaurant_table_id', $table->id)
            ->where('status', TableSessionStatus::Active)
            ->orderByDesc('id')
            ->lockForUpdate()
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        return $this->createNewActiveSession($table);
    }

    /**
     * ゲスト注文のみ: 卓に Active セッションが無い（未開局・会計済みのみ等）場合は作成せず拒否する。
     */
    public function requireActiveSessionForGuestOrder(RestaurantTable $table): TableSession
    {
        $existing = TableSession::query()
            ->where('restaurant_table_id', $table->id)
            ->where('status', TableSessionStatus::Active)
            ->orderByDesc('id')
            ->lockForUpdate()
            ->first();

        if ($existing === null) {
            throw new GuestOrderValidationException(__('guest.no_active_table_session'));
        }

        return $existing;
    }

    private function createNewActiveSession(RestaurantTable $table): TableSession
    {
        for ($i = 0; $i < 5; $i += 1) {
            $token = Str::lower(Str::random(48));

            $exists = TableSession::query()->where('token', $token)->exists();
            if ($exists) {
                continue;
            }

            return TableSession::query()->create([
                'shop_id' => $table->shop_id,
                'restaurant_table_id' => $table->id,
                'token' => $token,
                'status' => TableSessionStatus::Active,
                'opened_at' => now(),
                'closed_at' => null,
            ]);
        }

        throw new GuestOrderValidationException(__('Could not start table session.'));
    }
}
