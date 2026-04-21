<?php

namespace App\Actions\Pos\Discount;

use InvalidArgumentException;

/**
 * Immutable input DTO shared by {@see RecordItemDiscountAction},
 * {@see RecordOrderDiscountAction} and {@see RecordStaffDiscountAction}.
 *
 *   - operatorUserId : Filament session user (cashier driving the tablet).
 *   - approverStaffId: Staff member who typed the authorising PIN.
 *   - approverPin    : Raw PIN (verified inside the Action).
 *   - reason         : Free-form justification, required for audit.
 *   - idempotencyKey : Stable per-discount key. UNIQUE in discount_audit_logs
 *                      so the same logical apply cannot be recorded twice
 *                      even if the UI submits twice.
 *   - flatMinor / percentBasisPoints: exactly one must be provided (except
 *     for Staff discount which is fixed at 50%).
 */
final readonly class RecordDiscountRequest
{
    public function __construct(
        public int $shopId,
        public int $operatorUserId,
        public int $approverStaffId,
        public string $approverPin,
        public string $reason,
        public string $idempotencyKey,
        public ?int $flatMinor = null,
        public ?int $percentBasisPoints = null,
    ) {
        if ($this->shopId < 1 || $this->operatorUserId < 1 || $this->approverStaffId < 1) {
            throw new InvalidArgumentException('shopId, operatorUserId, approverStaffId must all be positive');
        }
        if (trim($this->approverPin) === '') {
            throw new InvalidArgumentException('approverPin is required');
        }
        if (trim($this->reason) === '') {
            throw new InvalidArgumentException('reason is required');
        }
        if (trim($this->idempotencyKey) === '') {
            throw new InvalidArgumentException('idempotencyKey is required');
        }
        if ($this->flatMinor !== null && $this->flatMinor < 0) {
            throw new InvalidArgumentException('flatMinor must be >= 0');
        }
        if ($this->percentBasisPoints !== null && ($this->percentBasisPoints < 0 || $this->percentBasisPoints > 10_000)) {
            throw new InvalidArgumentException('percentBasisPoints must be within [0, 10000]');
        }
    }
}
