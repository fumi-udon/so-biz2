<?php

namespace App\Domains\Pos\Discount;

use RuntimeException;

final class DiscountPolicy
{
    public const int REQUIRED_JOB_LEVEL = 3;

    public function assertAuthorized(AuthorizationContext $ctx, DiscountType $type): void
    {
        if ($ctx->actorJobLevel < self::REQUIRED_JOB_LEVEL) {
            throw new RuntimeException('Insufficient privilege for '.$type->value.' discount');
        }
    }
}
