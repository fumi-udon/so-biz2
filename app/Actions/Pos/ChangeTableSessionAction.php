<?php

namespace App\Actions\Pos;

use App\Domains\Pos\Tables\TableCategory;
use App\Enums\TableSessionManagementSource;
use App\Enums\TableSessionStatus;
use App\Exceptions\Pos\SessionManagedByPos2Exception;
use App\Exceptions\Pos\SessionRevisionMismatchException;
use App\Exceptions\Pos\TableAlreadyOccupiedException;
use App\Models\RestaurantTable;
use App\Models\TableSession;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class ChangeTableSessionAction
{
    public function execute(
        int $shopId,
        int $sourceTableSessionId,
        int $destTableId,
        int $expectedSessionRevision,
        TableSessionManagementSource $caller = TableSessionManagementSource::Legacy,
    ): void {
        DB::transaction(function () use ($shopId, $sourceTableSessionId, $destTableId, $expectedSessionRevision, $caller): void {
            $sourceSession = TableSession::query()
                ->where('shop_id', $shopId)
                ->whereKey($sourceTableSessionId)
                ->where('status', TableSessionStatus::Active)
                ->lockForUpdate()
                ->first();

            if ($sourceSession === null) {
                throw new RuntimeException(__('rad_table.active_session_not_found'));
            }

            if ($caller === TableSessionManagementSource::Legacy && $sourceSession->isManagedByPos2()) {
                throw SessionManagedByPos2Exception::forSession((int) $sourceSession->id);
            }

            if ((int) $sourceSession->session_revision !== $expectedSessionRevision) {
                throw new SessionRevisionMismatchException(
                    sessionId: (int) $sourceSession->id,
                    currentRevision: (int) $sourceSession->session_revision,
                    clientSentRevision: $expectedSessionRevision,
                );
            }

            $destTable = RestaurantTable::query()
                ->where('shop_id', $shopId)
                ->whereKey($destTableId)
                ->lockForUpdate()
                ->first();

            if ($destTable === null) {
                throw new RuntimeException(__('pos.table_not_found'));
            }
            if (TableCategory::tryResolveFromId((int) $destTable->id) !== TableCategory::Customer) {
                throw new RuntimeException(__('pos.change_table_invalid_target'));
            }

            if ((int) $sourceSession->restaurant_table_id === $destTableId) {
                return;
            }

            $destHasActiveSession = TableSession::query()
                ->where('shop_id', $shopId)
                ->where('restaurant_table_id', $destTableId)
                ->where('status', TableSessionStatus::Active)
                ->lockForUpdate()
                ->exists();

            if ($destHasActiveSession) {
                throw new TableAlreadyOccupiedException(
                    shopId: $shopId,
                    tableId: $destTableId,
                );
            }

            $sourceSession->restaurant_table_id = $destTableId;
            $sourceSession->session_revision = (int) $sourceSession->session_revision + 1;
            $sourceSession->save();
        });
    }
}
