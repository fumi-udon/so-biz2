<?php

namespace Tests\Unit\Pos;

use App\Domains\Pos\Discount\ApplyStaffDiscount;
use App\Domains\Pos\Discount\AuthorizationContext;
use App\Domains\Pos\Discount\DiscountPolicy;
use App\Domains\Pos\Tables\TableCategory;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

class DiscountPolicyTest extends TestCase
{
    #[Test]
    public function it_rejects_missing_reason_in_authorization_context(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new AuthorizationContext(actorUserId: 7, actorJobLevel: 3, reason: '');
    }

    #[Test]
    public function it_rejects_staff_discount_below_required_job_level(): void
    {
        $ctx = new AuthorizationContext(actorUserId: 7, actorJobLevel: 2, reason: 'staff meal');

        $this->expectException(RuntimeException::class);
        (new ApplyStaffDiscount(new DiscountPolicy))
            ->execute(10_000, TableCategory::Staff, $ctx);
    }

    #[Test]
    public function it_applies_staff_discount_for_job_level_3_or_above(): void
    {
        $ctx = new AuthorizationContext(actorUserId: 7, actorJobLevel: 3, reason: 'staff meal');
        $discount = (new ApplyStaffDiscount(new DiscountPolicy))
            ->execute(10_000, TableCategory::Staff, $ctx);

        $this->assertSame(5_000, $discount);
    }
}
