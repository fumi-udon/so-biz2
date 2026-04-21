<?php

namespace App\Domains\Pos\Discount;

use InvalidArgumentException;

final class ApplyOrderDiscount
{
    public function __construct(private readonly DiscountPolicy $policy) {}

    public function execute(
        int $orderSubtotalMinor,
        AuthorizationContext $ctx,
        ?int $flatMinor = null,
        ?int $percentBasisPoints = null,
    ): int {
        $this->policy->assertAuthorized($ctx, DiscountType::Order);

        if (($flatMinor === null) === ($percentBasisPoints === null)) {
            throw new InvalidArgumentException('Provide either flatMinor or percentBasisPoints');
        }
        if ($orderSubtotalMinor < 0) {
            throw new InvalidArgumentException('orderSubtotalMinor must be >= 0');
        }

        if ($flatMinor !== null) {
            return min($orderSubtotalMinor, max(0, $flatMinor));
        }

        $bp = max(0, min(10000, (int) $percentBasisPoints));

        return min($orderSubtotalMinor, intdiv($orderSubtotalMinor * $bp, 10000));
    }
}
