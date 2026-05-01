<?php

namespace App\Services\Pos;

use App\Enums\TableSessionManagementSource;
use App\Enums\TableSessionStatus;
use App\Exceptions\GuestOrderValidationException;
use App\Exceptions\Pos\SessionManagedByPos2Exception;
use App\Models\RestaurantTable;
use App\Models\TableSession;
use App\Models\TableSessionSettlement;
use Illuminate\Support\Str;

/**
 * ゲスト/スタッフ双方で、卓の有効 {@see TableSession} を一貫して取得/作成。
 */
final class TableSessionLifecycleService
{
    public function getOrCreateActiveSession(
        RestaurantTable $table,
        TableSessionManagementSource $caller = TableSessionManagementSource::Legacy,
    ): TableSession {
        $existing = TableSession::query()
            ->where('restaurant_table_id', $table->id)
            ->where('status', TableSessionStatus::Active)
            ->orderByDesc('id')
            ->lockForUpdate()
            ->first();

        if ($existing !== null) {
            // Defensive recovery: if this active row was already settled, do not
            // reuse it for new orders. Close it and continue with a fresh session.
            $alreadySettled = TableSessionSettlement::query()
                ->where('table_session_id', (int) $existing->id)
                ->lockForUpdate()
                ->exists();
            if ($alreadySettled) {
                $existing->forceFill([
                    'status' => TableSessionStatus::Closed,
                    'closed_at' => $existing->closed_at ?? now(),
                    'session_revision' => (int) $existing->session_revision + 1,
                ])->save();
            } else {
                if ($caller === TableSessionManagementSource::Legacy && $existing->isManagedByPos2()) {
                    throw SessionManagedByPos2Exception::forSession((int) $existing->id);
                }

                return $existing;
            }
        }

        return $this->createNewActiveSession($table, $caller);
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

        $alreadySettled = TableSessionSettlement::query()
            ->where('table_session_id', (int) $existing->id)
            ->lockForUpdate()
            ->exists();
        if ($alreadySettled) {
            $existing->forceFill([
                'status' => TableSessionStatus::Closed,
                'closed_at' => $existing->closed_at ?? now(),
                'session_revision' => (int) $existing->session_revision + 1,
            ])->save();
            throw new GuestOrderValidationException(__('guest.no_active_table_session'));
        }

        if ($existing->isManagedByPos2()) {
            throw new GuestOrderValidationException(__('pos.session_managed_by_pos2', ['id' => (int) $existing->id]));
        }

        return $existing;
    }

    private function createNewActiveSession(RestaurantTable $table, TableSessionManagementSource $source): TableSession
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
                'management_source' => $source,
            ]);
        }

        throw new GuestOrderValidationException(__('Could not start table session.'));
    }
}
