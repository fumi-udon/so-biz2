<?php

namespace App\Domains\Pos\Pricing;

use InvalidArgumentException;

final readonly class PricingResult
{
    public function __construct(
        public int $orderSubtotalMinor,
        public int $orderDiscountAppliedMinor,
        public int $totalBeforeRoundingMinor,
        public int $roundingAdjustmentMinor,
        public int $finalTotalMinor,
    ) {
        if ($this->orderSubtotalMinor < 0
            || $this->orderDiscountAppliedMinor < 0
            || $this->totalBeforeRoundingMinor < 0
            || $this->roundingAdjustmentMinor < 0
            || $this->finalTotalMinor < 0) {
            throw new InvalidArgumentException('pricing result cannot be negative');
        }

        if ($this->totalBeforeRoundingMinor - $this->roundingAdjustmentMinor !== $this->finalTotalMinor) {
            throw new InvalidArgumentException('pricing invariant violated');
        }
    }
}
