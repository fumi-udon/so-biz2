<?php

namespace App\Domains\Pos\Tables;

final class TableUiStatusResolver
{
    public function resolve(TableUiStatusInput $input): TableUiStatus
    {
        if (! $input->hasActiveSession) {
            return TableUiStatus::Free;
        }

        // Alert only while there are still unacknowledged new orders after print.
        // Once kitchen validation clears "placed", the tile should fall back to
        // Active (old print is stale, but no pending acknowledgement remains).
        if (
            $input->lastAdditionPrintedAt !== null
            && $input->hasOrdersAfterLastAdditionPrintedAt
            && $input->hasUnackedPlaced
        ) {
            return TableUiStatus::Alert;
        }

        // Once newly added orders after print are acknowledged, show Active (blue)
        // instead of Billed (yellow), because the previous addition print is stale.
        if (
            $input->lastAdditionPrintedAt !== null
            && $input->hasOrdersAfterLastAdditionPrintedAt
            && ! $input->hasUnackedPlaced
        ) {
            return TableUiStatus::Active;
        }

        if ($input->hasUnackedPlaced) {
            return TableUiStatus::Pending;
        }

        if ($input->lastAdditionPrintedAt !== null) {
            return TableUiStatus::Billed;
        }

        return TableUiStatus::Active;
    }
}
