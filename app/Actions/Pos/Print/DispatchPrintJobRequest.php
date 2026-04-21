<?php

namespace App\Actions\Pos\Print;

use App\Enums\PrintIntent;
use InvalidArgumentException;

/**
 * Request DTO for {@see DispatchPrintJobAction}.
 *
 * `sessionRevisionSnapshot` is the caller's expected session_revision at the
 * time the UI triggered the print. It is baked into the idempotency key so
 * two distinct "Addition" presses at different revisions produce two
 * distinct print_jobs rows — but a double-click at the same revision is
 * deduplicated to one row.
 */
final readonly class DispatchPrintJobRequest
{
    public function __construct(
        public int $shopId,
        public int $tableSessionId,
        public PrintIntent $intent,
        public int $sessionRevisionSnapshot,
        public string $payloadXml,
        /** @var array<string, mixed>|null */
        public ?array $payloadMeta = null,
        public ?int $orderId = null,
    ) {
        if ($shopId < 1 || $tableSessionId < 1) {
            throw new InvalidArgumentException('shopId and tableSessionId must be positive');
        }
        if ($sessionRevisionSnapshot < 0) {
            throw new InvalidArgumentException('sessionRevisionSnapshot must be >= 0');
        }
        if (trim($payloadXml) === '') {
            throw new InvalidArgumentException('payloadXml is required');
        }
    }

    public function idempotencyKey(): string
    {
        return hash(
            'sha256',
            $this->tableSessionId.':'.$this->sessionRevisionSnapshot.':'.$this->intent->value
        );
    }
}
