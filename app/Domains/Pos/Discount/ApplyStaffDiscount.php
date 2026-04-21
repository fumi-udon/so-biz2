<?php

namespace App\Domains\Pos\Discount;

use App\Domains\Pos\Tables\TableCategory;
use RuntimeException;

final class ApplyStaffDiscount
{
    public const int STAFF_DISCOUNT_BP = 5000;

    public function __construct(private readonly DiscountPolicy $policy) {}

    public function execute(int $orderSubtotalMinor, TableCategory $category, AuthorizationContext $ctx): int
    {
        $this->policy->assertAuthorized($ctx, DiscountType::Staff);

        if ($category !== TableCategory::Staff) {
            throw new RuntimeException('Staff discount only allowed for staff table category');
        }

        return intdiv(max(0, $orderSubtotalMinor) * self::STAFF_DISCOUNT_BP, 10000);
    }
}
