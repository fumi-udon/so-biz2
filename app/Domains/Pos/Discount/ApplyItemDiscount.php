<?php

namespace App\Domains\Pos\Discount;

use InvalidArgumentException;

final class ApplyItemDiscount
{
    public function __construct(private readonly DiscountPolicy $policy) {}

    public function execute(
        int $lineSubtotalMinor,
        AuthorizationContext $ctx,
        ?int $flatMinor = null,
        ?int $percentBasisPoints = null,
    ): int {
        $this->policy->assertAuthorized($ctx, DiscountType::Item);

        if (($flatMinor === null) === ($percentBasisPoints === null)) {
            throw new InvalidArgumentException('Provide either flatMinor or percentBasisPoints');
        }
        if ($lineSubtotalMinor < 0) {
            throw new InvalidArgumentException('lineSubtotalMinor must be >= 0');
        }

        if ($flatMinor !== null) {
            return min($lineSubtotalMinor, max(0, $flatMinor));
        }

        $bp = max(0, min(10000, (int) $percentBasisPoints));

        return min($lineSubtotalMinor, intdiv($lineSubtotalMinor * $bp, 10000));
    }
}
