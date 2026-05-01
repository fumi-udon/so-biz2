<?php

namespace App\Actions\RadTable;

use App\Enums\OrderStatus;
use App\Enums\TableSessionStatus;
use App\Exceptions\Pos\SessionManagedByPos2Exception;
use App\Exceptions\RevisionConflictException;
use App\Models\RestaurantTable;
use App\Models\TableSession;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class CheckoutTableSessionAction
{
    public function execute(int $shopId, int $tableSessionId, int $expectedSessionRevision): void
    {
        DB::transaction(function () use ($shopId, $tableSessionId, $expectedSessionRevision): void {
            $pre = TableSession::query()
                ->whereKey($tableSessionId)
                ->where('shop_id', $shopId)
                ->first();

            if ($pre === null) {
                throw new RuntimeException(__('rad_table.active_session_not_found'));
            }

            // Lock the physical table first, then the session, to match
            // {@see \App\Actions\GuestOrder\SubmitGuestOrderAction} and avoid
            // deadlocks with guest submit for the same table.
            $table = RestaurantTable::query()
                ->whereKey($pre->restaurant_table_id)
                ->where('shop_id', $shopId)
                ->lockForUpdate()
                ->first();

            if ($table === null) {
                throw new RuntimeException(__('rad_table.active_session_not_found'));
            }

            $session = TableSession::query()
                ->whereKey($tableSessionId)
                ->where('shop_id', $shopId)
                ->where('status', TableSessionStatus::Active)
                ->lockForUpdate()
                ->first();

            if ($session === null) {
                throw new RuntimeException(__('rad_table.active_session_not_found'));
            }

            if ($session->isManagedByPos2()) {
                throw SessionManagedByPos2Exception::forSession((int) $session->id);
            }

            if ((int) $session->session_revision !== $expectedSessionRevision) {
                throw new RevisionConflictException(
                    resource: 'table_session',
                    id: (int) $session->id,
                    currentRevision: (int) $session->session_revision,
                    clientSentRevision: $expectedSessionRevision,
                );
            }

            $hasUnacked = $session->posOrders()
                ->where('status', OrderStatus::Placed)
                ->exists();

            if ($hasUnacked) {
                throw new RuntimeException(__('rad_table.cannot_close_with_unacked'));
            }

            $session->update([
                'status' => TableSessionStatus::Closed,
                'closed_at' => now(),
                'last_addition_printed_at' => null,
            ]);
        });
    }
}
