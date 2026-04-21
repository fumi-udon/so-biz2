<?php

namespace Tests\Unit\Pos;

use App\Domains\Pos\Pricing\PricingEngine;
use App\Domains\Pos\Pricing\PricingInput;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PricingEngineTest extends TestCase
{
    #[Test]
    public function it_applies_floor_rounding_and_tracks_adjustment(): void
    {
        $engine = new PricingEngine;
        $result = $engine->calculate(new PricingInput(
            lineSubtotalsMinor: [23_180],
            lineDiscountsMinor: [0],
            orderDiscountMinor: 0,
        ));

        $this->assertSame(23_180, $result->totalBeforeRoundingMinor);
        $this->assertSame(23_100, $result->finalTotalMinor);
        $this->assertSame(80, $result->roundingAdjustmentMinor);
        $this->assertSame(
            $result->totalBeforeRoundingMinor - $result->roundingAdjustmentMinor,
            $result->finalTotalMinor,
        );
    }

    #[Test]
    public function it_is_idempotent_for_same_input(): void
    {
        $engine = new PricingEngine;
        $input = new PricingInput(
            lineSubtotalsMinor: [12_340, 5_050],
            lineDiscountsMinor: [340, 50],
            orderDiscountMinor: 500,
        );

        $a = $engine->calculate($input);
        $b = $engine->calculate($input);

        $this->assertSame($a->finalTotalMinor, $b->finalTotalMinor);
        $this->assertSame($a->roundingAdjustmentMinor, $b->roundingAdjustmentMinor);
        $this->assertSame($a->totalBeforeRoundingMinor, $b->totalBeforeRoundingMinor);
    }
}
