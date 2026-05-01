<?php

namespace App\Actions\Pos;

use App\Enums\PaymentMethod;
use App\Enums\TableSessionManagementSource;
use InvalidArgumentException;

/**
 * Immutable input DTO for {@see FinalizeTableSettlementAction}.
 *
 * Guards trivially-wrong callers at construction time so the Action body can
 * assume non-negative amounts, a valid PaymentMethod and a non-empty
 * bypass reason when `printBypassed` is true.
 */
final readonly class FinalizeTableSettlementRequest
{
    public function __construct(
        public int $shopId,
        public int $tableSessionId,
        public int $expectedSessionRevision,
        public int $tenderedMinor,
        public PaymentMethod $paymentMethod,
        public int $actorUserId,
        public bool $printBypassed = false,
        public ?string $bypassReason = null,
        public ?int $bypassedByUserId = null,
        // TEMP: POS_SETTLE_DEBUG correlation key (safe no-op in production flow)
        public ?string $debugTraceId = null,
        public TableSessionManagementSource $settlementInitiatedBy = TableSessionManagementSource::Legacy,
    ) {
        if ($this->shopId < 1) {
            throw new InvalidArgumentException('shopId must be positive');
        }
        if ($this->tableSessionId < 1) {
            throw new InvalidArgumentException('tableSessionId must be positive');
        }
        if ($this->expectedSessionRevision < 0) {
            throw new InvalidArgumentException('expectedSessionRevision must be >= 0');
        }
        if ($this->tenderedMinor < 0) {
            throw new InvalidArgumentException('tenderedMinor must be >= 0');
        }
        if ($this->actorUserId < 1) {
            throw new InvalidArgumentException('actorUserId must be positive');
        }
        if ($this->printBypassed) {
            if ($this->bypassReason === null || trim($this->bypassReason) === '') {
                throw new InvalidArgumentException(__('rad_table.bypass_reason_required'));
            }
            if ($this->bypassedByUserId === null || $this->bypassedByUserId < 1) {
                throw new InvalidArgumentException('bypassedByUserId is required when printBypassed=true');
            }
        }
    }
}
