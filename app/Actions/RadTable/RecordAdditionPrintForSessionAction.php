<?php

namespace App\Actions\RadTable;

use App\Enums\TableSessionStatus;
use App\Exceptions\RevisionConflictException;
use App\Models\TableSession;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class RecordAdditionPrintForSessionAction
{
    public function execute(int $shopId, int $tableSessionId, int $expectedSessionRevision): void
    {
        DB::transaction(function () use ($shopId, $tableSessionId, $expectedSessionRevision): void {
            $session = TableSession::query()
                ->whereKey($tableSessionId)
                ->where('shop_id', $shopId)
                ->where('status', TableSessionStatus::Active)
                ->lockForUpdate()
                ->first();

            if ($session === null) {
                throw new RuntimeException(__('rad_table.active_session_not_found'));
            }

            if ((int) $session->session_revision !== $expectedSessionRevision) {
                throw new RevisionConflictException(
                    resource: 'table_session',
                    id: (int) $session->id,
                    currentRevision: (int) $session->session_revision,
                    clientSentRevision: $expectedSessionRevision,
                );
            }

            $session->update([
                'last_addition_printed_at' => now(),
            ]);

            $session->increment('session_revision');
        });
    }
}
