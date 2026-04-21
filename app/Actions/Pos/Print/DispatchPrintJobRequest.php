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
 *
 * {@see PrintIntent::Copy}: optional `idempotencyNonce` is mixed into the key
 * so intentional reprints ("one more copy") are not collapsed into one job.
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
        public ?string $idempotencyNonce = null,
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
        if ($intent !== PrintIntent::Copy && $idempotencyNonce !== null && $idempotencyNonce !== '') {
            throw new InvalidArgumentException('idempotencyNonce is only supported for Copy intent');
        }
    }

    public function idempotencyKey(): string
    {
        $material = $this->tableSessionId.':'.$this->sessionRevisionSnapshot.':'.$this->intent->value;
        if ($this->intent === PrintIntent::Copy
            && $this->idempotencyNonce !== null
            && $this->idempotencyNonce !== '') {
            $material .= ':'.$this->idempotencyNonce;
        }

        return hash('sha256', $material);
    }
}
