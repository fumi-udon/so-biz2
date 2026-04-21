<?php

namespace App\Domains\Pos\Discount;

use InvalidArgumentException;

final readonly class AuthorizationContext
{
    public function __construct(
        public int $actorUserId,
        public int $actorJobLevel,
        public string $reason,
    ) {
        if ($this->actorUserId < 1) {
            throw new InvalidArgumentException('actorUserId is required');
        }
        if ($this->actorJobLevel < 1) {
            throw new InvalidArgumentException('actorJobLevel is required');
        }
        if (trim($this->reason) == '') {
            throw new InvalidArgumentException('reason is required');
        }
    }
}
