<?php

namespace App\Actions\Kds;

use App\Enums\OrderLineStatus;
use App\Exceptions\RevisionConflictException;
use App\Models\OrderLine;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

final class UpdateOrderLineStatusAction
{
    /**
     * @throws Throwable
     */
    public function execute(int $orderLineId, string $targetStatus, int $expectedLineRevision): OrderLine
    {
        $status = OrderLineStatus::tryFrom($targetStatus);
        if ($status === null) {
            throw new RuntimeException(__('kds.invalid_line_status'));
        }

        return DB::transaction(function () use ($orderLineId, $status, $expectedLineRevision): OrderLine {
            /** @var OrderLine|null $line */
            $line = OrderLine::query()->whereKey($orderLineId)->lockForUpdate()->first();

            if ($line === null) {
                throw new RuntimeException(__('pos.line_not_found'));
            }

            if ((int) $line->line_revision !== $expectedLineRevision) {
                throw new RevisionConflictException(
                    resource: 'order_line',
                    id: (int) $line->id,
                    currentRevision: (int) $line->line_revision,
                    clientSentRevision: $expectedLineRevision,
                );
            }

            $line->status = $status;
            $line->line_revision = (int) $line->line_revision + 1;
            $line->save();

            return $line->fresh() ?? $line;
        });
    }
}
